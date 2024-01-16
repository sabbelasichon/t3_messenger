<?php

declare(strict_types=1);

/*
 * This file is part of the "t3_messenger" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

use Psr\Cache\CacheItemPoolInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Ssch\Cache\Factory\Psr6Factory;
use Ssch\T3Messenger\Command\ShowConfigurationCommand;
use Ssch\T3Messenger\CommandToHandlerMapper;
use Ssch\T3Messenger\ConfigurationModuleProvider\MessengerProvider;
use Ssch\T3Messenger\DependencyInjection\Compiler\MessengerAlterTableListenerPass;
use Ssch\T3Messenger\DependencyInjection\Compiler\MessengerCommandToHandlerMapperPass;
use Ssch\T3Messenger\DependencyInjection\Compiler\MessengerMailerPass;
use Ssch\T3Messenger\DependencyInjection\Compiler\T3MessengerPass;
use Ssch\T3Messenger\DependencyInjection\MessengerConfigurationResolver;
use Ssch\T3Messenger\EventListener\AlterTableDefinitionStatementsEventListener;
use Ssch\T3Messenger\EventSubscriber\ExtbaseClearPersistenceStateWorkerSubscriber;
use Ssch\T3Messenger\Mailer\MailValidityResolver;
use Ssch\T3Messenger\Mailer\MessengerMailer;
use Ssch\T3Messenger\Middleware\LoggingMiddleware;
use Ssch\T3Messenger\Middleware\ServerRequestContextMiddleware;
use Ssch\T3Messenger\Middleware\ValidationMiddleware;
use Ssch\T3Messenger\Mime\BodyRenderer;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\EventDispatcher\DependencyInjection\RegisterListenersPass;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Mailer\Event\MessageEvent;
use Symfony\Component\Mailer\EventListener\MessageLoggerListener;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\MailerInterface as SymfonyMailerInterface;
use Symfony\Component\Mailer\Messenger\MessageHandler;
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
use Symfony\Component\Messenger\EventListener\SendFailedMessageForRetryListener;
use Symfony\Component\Messenger\EventListener\SendFailedMessageToFailureTransportListener;
use Symfony\Component\Messenger\EventListener\StopWorkerOnCustomStopExceptionListener;
use Symfony\Component\Messenger\EventListener\StopWorkerOnRestartSignalListener;
use Symfony\Component\Messenger\EventListener\StopWorkerOnSigtermSignalListener;
use Symfony\Component\Messenger\Handler\BatchHandlerInterface;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
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
use Symfony\Component\Messenger\Transport\TransportInterface;
use Symfony\Component\Mime\BodyRendererInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use TYPO3\CMS\Core\Database\Event\AlterTableDefinitionStatementsEvent;
use TYPO3\CMS\Core\DependencyInjection\ConsoleCommandPass;
use function Symfony\Component\DependencyInjection\Loader\Configurator\abstract_arg;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $containerConfigurator, ContainerBuilder $containerBuilder): void {
    if (! class_exists(\TYPO3\CMS\Core\Mail\Event\AfterMailerSentMessageEvent::class, false)) {
        class_alias(
            \Ssch\T3Messenger\Mailer\Event\AfterMailerSentMessageEvent::class,
            \TYPO3\CMS\Core\Mail\Event\AfterMailerSentMessageEvent::class
        );
    }

    if (! class_exists(\TYPO3\CMS\Core\Mail\Event\BeforeMailerSentMessageEvent::class, false)) {
        class_alias(
            \Ssch\T3Messenger\Mailer\Event\BeforeMailerSentMessageEvent::class,
            \TYPO3\CMS\Core\Mail\Event\BeforeMailerSentMessageEvent::class
        );
    }

    if (! interface_exists(\TYPO3\CMS\Core\Mail\MailerInterface::class, false)) {
        class_alias(
            \Ssch\T3Messenger\Mailer\Contract\MailerInterface::class,
            \TYPO3\CMS\Core\Mail\MailerInterface::class
        );
    }

    $services = $containerConfigurator->services();
    $services->defaults()
        ->private()
        ->autowire()
        ->autoconfigure();

    // Test configuration ignore
    $containerConfigurator->import(__DIR__ . '/../Classes/Test/Configuration/Services.php', null, true);

    $services->load('Ssch\\T3Messenger\\', __DIR__ . '/../Classes/')
        ->exclude([
            __DIR__ . '/../Classes/DependencyInjection',
            __DIR__ . '/../Classes/Test/Configuration',
            __DIR__ . '/../Classes/Test/Command',
        ]);

    $services->set(MessengerConfigurationResolver::class);

    // Schema Filter
    $services->set(AlterTableDefinitionStatementsEventListener::class)
        ->args([service('messenger.receiver_locator'), []])
        ->tag('event.listener', [
            'event' => AlterTableDefinitionStatementsEvent::class,
        ]);

    $services->set(CommandToHandlerMapper::class)->args([
        abstract_arg('passed by MessengerCommandToHandlerMapperPass'),
    ]);

    // Lowlevel Configuration Provider
    $services->set(MessengerProvider::class)
        ->tag(
            'lowlevel.configuration.module.provider',
            [
                'identifier' => 'messenger',
                'label' => 'Messenger Configuration',
                'after' => 'mfaProviders',
            ]
        );

    // Mailer
    $services->set(BodyRenderer::class)->public();
    $services->alias(BodyRendererInterface::class, BodyRenderer::class);
    $services->set('messenger.mailer.real_transport', TransportInterface::class)
        ->factory([service(\Ssch\T3Messenger\Mailer\RealTransportFactory::class), 'get']);
    $services->set('messenger.mailer.transport', TransportInterface::class)
        ->factory([service(\Ssch\T3Messenger\Mailer\TransportFactory::class), 'get']);
    $services->set(MessageHandler::class)
        ->arg('$transport', service('messenger.mailer.transport'))
        ->tag('messenger.message_handler');

    $services->set(MessengerMailer::class)
        ->args(
            [
                service(MessageBusInterface::class),
                service(MailValidityResolver::class),
                service(EventDispatcherInterface::class),
                service('messenger.mailer.real_transport'),
                service('messenger.mailer.transport'),
            ]
        )
        ->public();

    $services->alias(SymfonyMailerInterface::class, MessengerMailer::class);
    $services->alias(\TYPO3\CMS\Core\Mail\MailerInterface::class, MessengerMailer::class);
    $services->set('mailer.logger_message_listener', MessageLoggerListener::class)
        ->tag('event.listener', [
            'method' => 'onMessage',
            'event' => MessageEvent::class,
        ]);

    $services->set('event_dispatcher', EventDispatcher::class);

    $services->set('cache.messenger', CacheItemPoolInterface::class)
        ->factory([service(Psr6Factory::class), 'create'])
        ->args(['t3_messenger']);
    $services->alias('cache.messenger.restart_workers_signal', 'cache.messenger');

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
        ->set('messenger.middleware.logging', LoggingMiddleware::class)
        ->set('messenger.middleware.server_request_context', ServerRequestContextMiddleware::class)
        // Discovery
        ->set('messenger.receiver_locator', ServiceLocator::class)
        ->args([[]])
        ->tag('container.service_locator')

        // Transports
        ->set('messenger.transport_factory', TransportFactory::class)
        ->args([tagged_iterator('messenger.transport_factory')])
        ->autoconfigure(false)
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
        ->set(
            'messenger.listener.extbase_persistence_clear_state_listener',
            ExtbaseClearPersistenceStateWorkerSubscriber::class
        )
        ->tag('kernel.event_subscriber')
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
            null,
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

    if (PHP_VERSION_ID < 80000) {
        $containerBuilder->registerAttributeForAutoconfiguration(
            AsMessageHandler::class,
            static function (ChildDefinition $definition, AsMessageHandler $attribute): void {
                $tagAttributes = get_object_vars($attribute);
                $tagAttributes['from_transport'] = $tagAttributes['fromTransport'];
                unset($tagAttributes['fromTransport']);
                $definition->addTag('messenger.message_handler', $tagAttributes);
            }
        );
    } else {
        $containerBuilder->registerAttributeForAutoconfiguration(
            AsMessageHandler::class,
            static function (ChildDefinition $definition, AsMessageHandler $attribute, $reflector): void {
                $tagAttributes = get_object_vars($attribute);
                $tagAttributes['from_transport'] = $tagAttributes['fromTransport'];
                unset($tagAttributes['fromTransport']);
                if ($reflector instanceof \ReflectionMethod) {
                    if (isset($tagAttributes['method'])) {
                        throw new LogicException(sprintf(
                            'AsMessageHandler attribute cannot declare a method on "%s::%s()".',
                            $reflector->class,
                            $reflector->name
                        ));
                    }
                    $tagAttributes['method'] = $reflector->getName();
                }
                $definition->addTag('messenger.message_handler', $tagAttributes);
            }
        );
    }

    // Register autoconfiguration for transports
    $containerBuilder->registerForAutoconfiguration(TransportFactoryInterface::class)
        ->addTag('messenger.transport_factory');

    $containerBuilder->registerForAutoconfiguration(BatchHandlerInterface::class)
        ->addTag('messenger.message_handler');

    $containerBuilder->registerForAutoconfiguration(EventSubscriberInterface::class)
        ->addTag('kernel.event_subscriber');

    $shouldAddRegisterListenersPass = true;
    $beforeRemovingPasses = $containerBuilder->getCompilerPassConfig()
        ->getBeforeRemovingPasses();
    foreach ($beforeRemovingPasses as $beforeRemovingPass) {
        if ($beforeRemovingPass instanceof RegisterListenersPass) {
            $shouldAddRegisterListenersPass = false;
            break;
        }
    }

    if ($shouldAddRegisterListenersPass) {
        // Compiler passes
        $registerListenersPass = new RegisterListenersPass();
        if (class_exists(ConsoleEvents::class) && method_exists($registerListenersPass, 'setNoPreloadEvents')) {
            $registerListenersPass->setNoPreloadEvents([
                ConsoleEvents::COMMAND,
                ConsoleEvents::TERMINATE,
                ConsoleEvents::ERROR,
            ]);
        }
        // must be registered before removing private services as some might be listeners/subscribers
        // but as late as possible to get resolved parameters
        $containerBuilder->addCompilerPass($registerListenersPass, PassConfig::TYPE_BEFORE_REMOVING);
    }

    $services->set(ShowConfigurationCommand::class)->tag('console.command', [
        'command' => 't3_messenger:show-configuration',
    ]);

    $containerBuilder->addCompilerPass(new MessengerMailerPass('event.listener'));
    $containerBuilder->addCompilerPass(new T3MessengerPass(new MessengerConfigurationResolver()));
    $containerBuilder->addCompilerPass(new MessengerPass());
    $containerBuilder->addCompilerPass(new ConsoleCommandPass('console.command'));
    $containerBuilder->addCompilerPass(new MessengerCommandToHandlerMapperPass());
    $containerBuilder->addCompilerPass(new MessengerAlterTableListenerPass());
};
