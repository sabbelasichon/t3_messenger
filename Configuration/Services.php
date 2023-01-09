<?php

declare(strict_types=1);

/*
 * This file is part of the "t3_tactician" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

use Ssch\T3Messenger\Console\MyDummyConsoleCommand;
use Ssch\T3Messenger\DependencyInjection\Compiler\T3MessengerPass;
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
use Symfony\Component\Messenger\Command\StopWorkersCommand;
use Symfony\Component\Messenger\DependencyInjection\MessengerPass;
use Symfony\Component\Messenger\EventListener\AddErrorDetailsStampListener;
use Symfony\Component\Messenger\EventListener\DispatchPcntlSignalListener;
use Symfony\Component\Messenger\EventListener\SendFailedMessageForRetryListener;
use Symfony\Component\Messenger\EventListener\SendFailedMessageToFailureTransportListener;
use Symfony\Component\Messenger\EventListener\StopWorkerOnRestartSignalListener;
use Symfony\Component\Messenger\EventListener\StopWorkerOnSigtermSignalListener;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;
use Symfony\Component\Messenger\Middleware\AddBusNameStampMiddleware;
use Symfony\Component\Messenger\Middleware\DispatchAfterCurrentBusMiddleware;
use Symfony\Component\Messenger\Middleware\FailedMessageProcessingMiddleware;
use Symfony\Component\Messenger\Middleware\HandleMessageMiddleware;
use Symfony\Component\Messenger\Middleware\RejectRedeliveredMessageMiddleware;
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

return static function (ContainerConfigurator $containerConfigurator, ContainerBuilder $containerBuilder): void {
    $services = $containerConfigurator->services();
    $services->defaults()
        ->private()
        ->autowire()
        ->autoconfigure();

    $services->load('Ssch\\T3Messenger\\', __DIR__ . '/../Classes/')->exclude([__DIR__ . '/../Classes/Command']);

    $services->set('event_dispatcher', EventDispatcher::class);

    $services
        ->alias('messenger.default_serializer', 'messenger.transport.native_php_serializer')
        ->alias(SerializerInterface::class, 'messenger.default_serializer')

        // Asynchronous
        ->set('messenger.senders_locator', SendersLocator::class)
        ->args([abstract_arg('per message senders map'), abstract_arg('senders service locator')])
        ->set('messenger.middleware.send_message', SendMessageMiddleware::class)
        ->args([service('messenger.senders_locator'), service('event_dispatcher')])

        // Message encoding/decoding
        ->set('messenger.transport.symfony_serializer', Serializer::class)
        ->args([service('serializer'), abstract_arg('format'), abstract_arg('context')])
        ->set('serializer.normalizer.flatten_exception', FlattenExceptionNormalizer::class)
        ->tag('serializer.normalizer', [
            'priority' => -880,
        ])
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
        // worker event listener
        ->set('messenger.retry.send_failed_message_for_retry_listener', SendFailedMessageForRetryListener::class)
        ->args([
            abstract_arg('senders service locator'),
            service('messenger.retry_strategy_locator'),
            service('event_dispatcher'),
        ])
        ->set('messenger.failure.add_error_details_stamp_listener', AddErrorDetailsStampListener::class)
        ->set(
            'messenger.failure.send_failed_message_to_failure_transport_listener',
            SendFailedMessageToFailureTransportListener::class
        )
        ->args([abstract_arg('failure transports')])

        ->set('messenger.listener.dispatch_pcntl_signal_listener', DispatchPcntlSignalListener::class)

//        ->set('messenger.listener.stop_worker_on_restart_signal_listener', StopWorkerOnRestartSignalListener::class)
//        ->args([service('cache.messenger.restart_workers_signal')])

        ->set('messenger.listener.stop_worker_on_sigterm_signal_listener', StopWorkerOnSigtermSignalListener::class)

        ->set('messenger.routable_message_bus', RoutableMessageBus::class)
        ->args([abstract_arg('message bus locator'), service('messenger.default_bus')]);

    // Add messenger commands
    $services->set('console.command.messenger_debug', DebugCommand::class)
        ->args([
            [], // Message to handlers mapping
        ])
        ->tag('console.command', [
            'command' => 't3_messenger:debug',
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
        ->args([abstract_arg('Default failure receiver name'), abstract_arg('Receivers')]);

    $services->set('console.command.messenger_failed_messages_remove', FailedMessagesRemoveCommand::class)
        ->args([abstract_arg('Default failure receiver name'), abstract_arg('Receivers')]);

    //    $services->set('console.command.messenger_stop_workers', StopWorkersCommand::class)
    //        ->args([
    //            service('cache.messenger.restart_workers_signal'),
    //        ])
    //        ->tag('console.command');

    $services->set('console.command.messenger_consume_messages', ConsumeMessagesCommand::class)
        ->args([
            abstract_arg('Routable message bus'),
            service('messenger.receiver_locator'),
            service('event_dispatcher'),
            abstract_arg('messenger logger'),
            [], // Receiver names
        ])
        ->tag('console.command', [
            'command' => 't3_messenger:consume-messages',
        ]);

    $services->set('console.command.messenger_setup_transports', SetupTransportsCommand::class)
        ->args([service('messenger.receiver_locator'), []])
        ->tag('console.command', [
            'command' => 't3_messenger:setup-transports',
        ]);

    $services->set(MyDummyConsoleCommand::class)
        ->tag('console.command', [
            'command' => 't3_messenger:dummy-dispatch',
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

    $containerBuilder->addCompilerPass($registerListenersPass, PassConfig::TYPE_BEFORE_REMOVING);
    $containerBuilder->addCompilerPass(new T3MessengerPass());
    $containerBuilder->addCompilerPass(new MessengerPass());
};
