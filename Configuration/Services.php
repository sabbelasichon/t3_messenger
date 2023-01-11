<?php

declare(strict_types=1);

/*
 * This file is part of the "t3_messenger" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

use Ssch\T3Messenger\Cache\Psr6CacheAdapter;
use Ssch\T3Messenger\DependencyInjection\Compiler\T3MessengerPass;
use Ssch\T3Messenger\DependencyInjection\MessengerConfigurationResolver;
use Ssch\T3Messenger\Middleware\ValidationMiddleware;
use Ssch\T3Messenger\Routing\RequestContextAwareFactory;
use Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use function Symfony\Component\DependencyInjection\Loader\Configurator\abstract_arg;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\EventDispatcher\DependencyInjection\RegisterListenersPass;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Bridge\AmazonSqs\Transport\AmazonSqsTransportFactory;
use Symfony\Component\Messenger\Bridge\Amqp\Transport\AmqpTransportFactory;
use Symfony\Component\Messenger\Bridge\Beanstalkd\Transport\BeanstalkdTransportFactory;
use Symfony\Component\Messenger\Bridge\Redis\Transport\RedisTransportFactory;
use Symfony\Component\Messenger\Command\ConsumeMessagesCommand;
use Symfony\Component\Messenger\Command\DebugCommand;
use Symfony\Component\Messenger\Command\FailedMessagesRemoveCommand;
use Symfony\Component\Messenger\Command\FailedMessagesRetryCommand;
use Symfony\Component\Messenger\Command\FailedMessagesShowCommand;
use Symfony\Component\Messenger\Command\SetupTransportsCommand;
use Symfony\Component\Messenger\Command\StatsCommand;
use Symfony\Component\Messenger\Command\StopWorkersCommand;
use Symfony\Component\Messenger\DependencyInjection\MessengerPass;
use Symfony\Component\Messenger\EventListener\AddErrorDetailsStampListener;
use Symfony\Component\Messenger\EventListener\DispatchPcntlSignalListener;
use Symfony\Component\Messenger\EventListener\ResetServicesListener;
use Symfony\Component\Messenger\EventListener\SendFailedMessageForRetryListener;
use Symfony\Component\Messenger\EventListener\SendFailedMessageToFailureTransportListener;
use Symfony\Component\Messenger\EventListener\StopWorkerOnCustomStopExceptionListener;
use Symfony\Component\Messenger\EventListener\StopWorkerOnRestartSignalListener;
use Symfony\Component\Messenger\EventListener\StopWorkerOnSigtermSignalListener;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;
use Symfony\Component\Messenger\Middleware\AddBusNameStampMiddleware;
use Symfony\Component\Messenger\Middleware\DispatchAfterCurrentBusMiddleware;
use Symfony\Component\Messenger\Middleware\FailedMessageProcessingMiddleware;
use Symfony\Component\Messenger\Middleware\HandleMessageMiddleware;
use Symfony\Component\Messenger\Middleware\RejectRedeliveredMessageMiddleware;
use Symfony\Component\Messenger\Middleware\RouterContextMiddleware;
use Symfony\Component\Messenger\Middleware\SendMessageMiddleware;
use Symfony\Component\Messenger\Retry\MultiplierRetryStrategy;
use Symfony\Component\Messenger\RoutableMessageBus;
use Symfony\Component\Messenger\Transport\InMemoryTransportFactory;
use Symfony\Component\Messenger\Transport\Sender\SendersLocator;
use Symfony\Component\Messenger\Transport\Serialization\Normalizer\FlattenExceptionNormalizer;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;
use Symfony\Component\Messenger\Transport\Serialization\Serializer;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\Sync\SyncTransportFactory;
use Symfony\Component\Messenger\Transport\TransportFactory;
use Symfony\Component\Messenger\Transport\TransportFactoryInterface;
use Symfony\Component\Routing\RequestContextAwareInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\DependencyInjection\ConsoleCommandPass;

return static function (ContainerConfigurator $containerConfigurator, ContainerBuilder $containerBuilder): void {
    $services = $containerConfigurator->services();
    $services->defaults()
        ->private()
        ->autowire()
        ->autoconfigure();

    $services->load('Ssch\\T3Messenger\\', __DIR__ . '/../Classes/')->exclude([__DIR__ . '/../Classes/Command']);

    $services->set(HttpFoundationFactory::class);
    $services->set('router')
        ->class(RequestContextAwareInterface::class)
        ->factory([service(RequestContextAwareFactory::class), 'create']);
    $services->set('event_dispatcher', EventDispatcher::class);
    $services->set('cache.messenger', FrontendInterface::class)
        ->factory([service(CacheManager::class), 'getCache'])
        ->args(['t3_messenger']);

    $services->set(Psr6CacheAdapter::class)->args([service('cache.messenger')]);
    $services->alias('cache.messenger.restart_workers_signal', Psr6CacheAdapter::class);

    $services
        ->alias('messenger.default_serializer', 'messenger.transport.native_php_serializer')
        ->alias(SerializerInterface::class, 'messenger.default_serializer')

        // Asynchronous
        ->set('messenger.senders_locator', SendersLocator::class)
        ->args([abstract_arg('per message senders map'), abstract_arg('senders service locator')])
        ->set('messenger.middleware.send_message', SendMessageMiddleware::class)
        ->abstract()
        ->args([service('messenger.senders_locator'), service('event_dispatcher')])
        ->tag('psr.logger_aware')

        // Message encoding/decoding
        ->set('messenger.transport.symfony_serializer', Serializer::class)
        ->args([service('serializer')->nullOnInvalid(), abstract_arg('format'), abstract_arg('context')])
        ->set('messenger.transport.native_php_serializer', PhpSerializer::class)
        // Middleware
        ->set('messenger.middleware.handle_message', HandleMessageMiddleware::class)
        ->abstract()
        ->args([abstract_arg('bus handler resolver')])
        ->set('messenger.middleware.add_bus_name_stamp_middleware', AddBusNameStampMiddleware::class)
        ->abstract()
        ->set('messenger.middleware.dispatch_after_current_bus', DispatchAfterCurrentBusMiddleware::class)
        ->set('messenger.middleware.reject_redelivered_message_middleware', RejectRedeliveredMessageMiddleware::class)
        ->set('messenger.middleware.failed_message_processing_middleware', FailedMessageProcessingMiddleware::class)
        ->set('messenger.middleware.validation', ValidationMiddleware::class)
        ->set('messenger.middleware.router_context', RouterContextMiddleware::class)
        ->args([service('router')])
        ->abstract()
        // Discovery
        ->set('messenger.receiver_locator', ServiceLocator::class)
        ->args([[]])
        ->tag('container.service_locator')

        // Transports
        ->set('messenger.transport_factory', TransportFactory::class)
        ->args([tagged_iterator('messenger.transport_factory')])
        ->set('messenger.transport.amqp.factory', AmqpTransportFactory::class)
        ->set('messenger.transport.redis.factory', RedisTransportFactory::class)
        ->set('messenger.transport.sync.factory', SyncTransportFactory::class)
        ->args([service('messenger.routable_message_bus')])
        ->tag('messenger.transport_factory')
        ->set('messenger.transport.in_memory.factory', InMemoryTransportFactory::class)
        ->tag('messenger.transport_factory')
        ->set('messenger.transport.sqs.factory', AmazonSqsTransportFactory::class)
        ->args([abstract_arg('messenger logger')])
        ->set('messenger.transport.beanstalkd.factory', BeanstalkdTransportFactory::class)
        // retry
        ->set('messenger.retry_strategy_locator', ServiceLocator::class)
        ->args([[]])
        ->tag('container.service_locator')
        ->set('messenger.retry.abstract_multiplier_retry_strategy', MultiplierRetryStrategy::class)
        ->abstract()
        ->args([
            abstract_arg('max retries'),
            abstract_arg('delay ms'),
            abstract_arg('multiplier'),
            abstract_arg('max delay ms'),
        ])

        // rate limiter
        ->set('messenger.rate_limiter_locator', ServiceLocator::class)
        ->args([[]])
        ->tag('container.service_locator')

        // worker event listener
        ->set('messenger.retry.send_failed_message_for_retry_listener', SendFailedMessageForRetryListener::class)
        ->args([
            abstract_arg('senders service locator'),
            service('messenger.retry_strategy_locator'),
            abstract_arg('messenger logger'),
            service('event_dispatcher'),
        ])
        ->tag('kernel.event_subscriber')
        ->set('messenger.failure.add_error_details_stamp_listener', AddErrorDetailsStampListener::class)
        ->tag('kernel.event_subscriber')
        ->set(
            'messenger.failure.send_failed_message_to_failure_transport_listener',
            SendFailedMessageToFailureTransportListener::class
        )
        ->args([abstract_arg('failure transports')])
        ->tag('kernel.event_subscriber')
        ->set('messenger.listener.dispatch_pcntl_signal_listener', DispatchPcntlSignalListener::class)
        ->tag('kernel.event_subscriber')
        ->set('messenger.listener.stop_worker_on_restart_signal_listener', StopWorkerOnRestartSignalListener::class)
        ->args([service('cache.messenger.restart_workers_signal')])
        ->tag('kernel.event_subscriber')
        ->set(
            'messenger.listener.stop_worker_on_stop_exception_listener',
            StopWorkerOnCustomStopExceptionListener::class
        )
        ->tag('kernel.event_subscriber')
        ->set('messenger.listener.stop_worker_on_sigterm_signal_listener', StopWorkerOnSigtermSignalListener::class)
        ->args([abstract_arg('messenger logger')])
        ->tag('kernel.event_subscriber')

//        ->set('messenger.listener.reset_services', ResetServicesListener::class)
//        ->args([
//            service('services_resetter'),
//        ])

        ->set('messenger.routable_message_bus', RoutableMessageBus::class)
        ->args([abstract_arg('message bus locator'), service('messenger.default_bus')]);

    // Add Normalizer if symfony serializer is available
    if (interface_exists(DenormalizerInterface::class)) {
        $services->set('serializer.normalizer.flatten_exception', FlattenExceptionNormalizer::class)
            ->tag('serializer.normalizer', [
                'priority' => -880,
            ]);
    }

    // Add messenger commands
    $services->set('console.command.messenger_debug', DebugCommand::class)
        ->args([
            [], // Message to handlers mapping
        ])
        ->tag('console.command', [
            'command' => 't3_messenger:debug',
            'schedulable' => false,
        ]);

    $services->set('console.command.messenger_failed_messages_retry', FailedMessagesRetryCommand::class)
        ->args([
            abstract_arg('Default failure receiver name'),
            abstract_arg('Receivers'),
            service('messenger.routable_message_bus'),
            service('event_dispatcher'),
            abstract_arg('messenger logger'),
        ]);

    $services->set('console.command.messenger_failed_messages_show', FailedMessagesShowCommand::class)
        ->args([
            abstract_arg('Default failure receiver name'),
            abstract_arg('Receivers'),
            service('messenger.transport.native_php_serializer')
                ->nullOnInvalid(),
        ]);

    $services->set('console.command.messenger_failed_messages_remove', FailedMessagesRemoveCommand::class)
        ->args([
            abstract_arg('Default failure receiver name'),
            abstract_arg('Receivers'),
            service('messenger.transport.native_php_serializer')
                ->nullOnInvalid(),
        ]);

    $services->set('console.command.messenger_stop_workers', StopWorkersCommand::class)
        ->args([service('cache.messenger.restart_workers_signal')])
        ->tag('console.command', [
            'command' => 't3_messenger:stop-workers',
            'schedulable' => false,
        ]);

    $services->set('console.command.messenger_consume_messages', ConsumeMessagesCommand::class)
        ->args([
            abstract_arg('Routable message bus'),
            service('messenger.receiver_locator'),
            service('event_dispatcher'),
            abstract_arg('messenger logger'),
            [], // Receiver names
            service('messenger.listener.reset_services')
                ->nullOnInvalid(),
            [], // Bus names
            service('messenger.rate_limiter_locator')
                ->nullOnInvalid(),
        ])
        ->tag('console.command', [
            'command' => 't3_messenger:consume-messages',
            'schedulable' => false,
        ]);

    if (class_exists(StatsCommand::class)) {
        $services->set('console.command.messenger_stats', StatsCommand::class)
            ->args([service('messenger.receiver_locator'), abstract_arg('Receivers names')])
            ->tag('console.command', [
                'command' => 't3_messenger:message-stats',
                'schedulable' => false,
            ]);
    }

    $services->set('console.command.messenger_setup_transports', SetupTransportsCommand::class)
        ->args([service('messenger.receiver_locator'), []])
        ->tag('console.command', [
            'command' => 't3_messenger:setup-transports',
            'schedulable' => false,
        ]);

    // Register autoconfiguration for message handlers via interface or attributes
    $containerBuilder->registerForAutoconfiguration(MessageHandlerInterface::class)
        ->addTag('messenger.message_handler');
    $containerBuilder->registerAttributeForAutoconfiguration(
        AsMessageHandler::class,
        static function (ChildDefinition $definition, AsMessageHandler $attribute): void {
            $tagAttributes = get_object_vars($attribute);
            $tagAttributes['from_transport'] = $tagAttributes['fromTransport'];
            unset($tagAttributes['fromTransport']);
            $definition->addTag('messenger.message_handler', $tagAttributes);
        }
    );

    // Register autoconfiguration for transports
    $containerBuilder->registerForAutoconfiguration(TransportFactoryInterface::class)
        ->addTag('messenger.transport_factory');

    // Compiler passes
    $registerListenersPass = new RegisterListenersPass();
    if (class_exists(ConsoleEvents::class)) {
        $registerListenersPass->setNoPreloadEvents([
            ConsoleEvents::COMMAND,
            ConsoleEvents::TERMINATE,
            ConsoleEvents::ERROR,
        ]);
    }

    // must be registered before removing private services as some might be listeners/subscribers
    // but as late as possible to get resolved parameters
    $containerBuilder->addCompilerPass($registerListenersPass, PassConfig::TYPE_BEFORE_REMOVING);
    $containerBuilder->addCompilerPass(new T3MessengerPass(new MessengerConfigurationResolver()));
    $containerBuilder->addCompilerPass(new MessengerPass());
    $containerBuilder->addCompilerPass(new ConsoleCommandPass('console.command'));
};
