<?php

declare(strict_types=1);

/*
 * This file is part of the "t3_messenger" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

namespace Ssch\T3Messenger\DependencyInjection\Compiler;

use Ssch\T3Messenger\ConfigurationModuleProvider\MessengerProvider;
use Ssch\T3Messenger\DependencyInjection\MessengerConfigurationCollector;
use Ssch\T3Messenger\DependencyInjection\MessengerConfigurationResolver;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\ServiceLocatorTagPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Messenger\Bridge\AmazonSqs\Transport\AmazonSqsTransportFactory;
use Symfony\Component\Messenger\Bridge\Amqp\Transport\AmqpTransportFactory;
use Symfony\Component\Messenger\Bridge\Beanstalkd\Transport\BeanstalkdTransportFactory;
use Symfony\Component\Messenger\Bridge\Redis\Transport\RedisTransportFactory;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;
use Symfony\Component\RateLimiter\LimiterInterface;
use TYPO3\CMS\Core\Core\Bootstrap;
use TYPO3\CMS\Core\Log\Channel;
use TYPO3\CMS\Core\Log\Logger;
use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\CMS\Core\Package\PackageManager;
use TYPO3\CMS\Lowlevel\ConfigurationModuleProvider\AbstractProvider;

final class T3MessengerPass implements CompilerPassInterface
{
    private MessengerConfigurationResolver $messengerConfigurationResolver;

    public function __construct(MessengerConfigurationResolver $messengerConfigurationResolver)
    {
        $this->messengerConfigurationResolver = $messengerConfigurationResolver;
    }

    public function process(ContainerBuilder $container): void
    {
        if (! interface_exists(MessageBusInterface::class)) {
            throw new LogicException(
                'Messenger support cannot be enabled as the Messenger component is not installed. Try running "composer require symfony/messenger".'
            );
        }

        $config = $this->collectMessengerConfigurationsFromPackages();

        if (count($config) === 0) {
            return;
        }

        if (class_exists(AmqpTransportFactory::class)) {
            $container->getDefinition('messenger.transport.amqp.factory')
                ->addTag('messenger.transport_factory');
        }

        if (class_exists(RedisTransportFactory::class)) {
            $container->getDefinition('messenger.transport.redis.factory')
                ->addTag('messenger.transport_factory');
        }

        if (class_exists(AmazonSqsTransportFactory::class)) {
            $this->addLoggerArgument($container, 'messenger.transport.sqs.factory', 0);

            $container->getDefinition('messenger.transport.sqs.factory')
                ->addTag('messenger.transport_factory');
        }

        if (class_exists(BeanstalkdTransportFactory::class)) {
            $container->getDefinition('messenger.transport.beanstalkd.factory')
                ->addTag('messenger.transport_factory');
        }

        if (! class_exists(AbstractProvider::class)) {
            $container->removeDefinition(MessengerProvider::class);
        }

        if ($config['default_bus'] === null && \count($config['buses']) === 1) {
            $config['default_bus'] = key($config['buses']);
        }

        $defaultMiddleware = [
            'before' => [
                [
                    'id' => 'add_bus_name_stamp_middleware',
                ],
                [
                    'id' => 'reject_redelivered_message_middleware',
                ],
                [
                    'id' => 'dispatch_after_current_bus',
                ],
                [
                    'id' => 'failed_message_processing_middleware',
                ],
            ],
            'after' => [
                [
                    'id' => 'send_message',
                ],
                [
                    'id' => 'handle_message',
                ],
            ],
        ];

        foreach ($config['buses'] as $busId => $bus) {
            $middleware = $bus['middleware'];

            if ($bus['default_middleware']['enabled']) {
                $defaultMiddleware['after'][0]['arguments'] = [$bus['default_middleware']['allow_no_senders']];
                $defaultMiddleware['after'][1]['arguments'] = [$bus['default_middleware']['allow_no_handlers']];

                // argument to add_bus_name_stamp_middleware
                $defaultMiddleware['before'][0]['arguments'] = [$busId];

                $middleware = array_merge($defaultMiddleware['before'], $middleware, $defaultMiddleware['after']);
            }

            $container->setParameter($busId . '.middleware', $middleware);
            $container->register($busId, MessageBus::class)->addArgument([])->addTag('messenger.bus');

            if ($busId === $config['default_bus']) {
                $container->setAlias('messenger.default_bus', $busId)
                    ->setPublic(true);
                $container->setAlias(MessageBusInterface::class, $busId);
            } else {
                $container->registerAliasForArgument($busId, MessageBusInterface::class);
            }
        }

        if ($config['transports'] === [] || $config['transports'] === null) {
            $container->removeDefinition('messenger.transport.symfony_serializer');
            $container->removeDefinition('messenger.transport.amqp.factory');
            $container->removeDefinition('messenger.transport.redis.factory');
            $container->removeDefinition('messenger.transport.sqs.factory');
            $container->removeDefinition('messenger.transport.beanstalkd.factory');
            $container->removeAlias(SerializerInterface::class);
        } else {
            $container->getDefinition('messenger.transport.symfony_serializer')
                ->replaceArgument(1, $config['serializer']['symfony_serializer']['format'])
                ->replaceArgument(2, $config['serializer']['symfony_serializer']['context']);
            $container->setAlias('messenger.default_serializer', $config['serializer']['default_serializer']);
        }

        $failureTransports = [];
        if ($config['failure_transport']) {
            if (! isset($config['transports'][$config['failure_transport']])) {
                throw new LogicException(sprintf(
                    'Invalid Messenger configuration: the failure transport "%s" is not a valid transport or service id.',
                    $config['failure_transport']
                ));
            }

            $container->setAlias(
                'messenger.failure_transports.default',
                'messenger.transport.' . $config['failure_transport']
            );
            $failureTransports[] = $config['failure_transport'];
        }

        $failureTransportsByName = [];
        foreach ($config['transports'] as $name => $transport) {
            if (isset($transport['failure_transport'])) {
                $failureTransports[] = $transport['failure_transport'];
                $failureTransportsByName[$name] = $transport['failure_transport'];
            } elseif ($config['failure_transport']) {
                $failureTransportsByName[$name] = $config['failure_transport'];
            }
        }

        $senderAliases = [];
        $transportRetryReferences = [];
        $transportRateLimiterReferences = [];
        foreach ($config['transports'] as $name => $transport) {
            $serializerId = $transport['serializer'] ?? 'messenger.default_serializer';
            $transportDefinition = (new Definition(TransportInterface::class))
                ->setFactory([new Reference('messenger.transport_factory'), 'createTransport'])
                ->setArguments([
                    $transport['dsn'],
                    $transport['options'] + [
                        'transport_name' => $name,
                    ],
                    new Reference($serializerId),
                ])
                ->addTag(
                    'messenger.receiver',
                    [
                        'alias' => $name,
                        'is_failure_transport' => \in_array($name, $failureTransports, true),
                    ]
                );
            $container->setDefinition($transportId = 'messenger.transport.' . $name, $transportDefinition);
            $senderAliases[$name] = $transportId;

            if ($transport['retry_strategy']['service'] !== null) {
                $transportRetryReferences[$name] = new Reference($transport['retry_strategy']['service']);
            } else {
                $retryServiceId = sprintf('messenger.retry.multiplier_retry_strategy.%s', $name);
                $retryDefinition = new ChildDefinition('messenger.retry.abstract_multiplier_retry_strategy');
                $retryDefinition
                    ->replaceArgument(0, $transport['retry_strategy']['max_retries'])
                    ->replaceArgument(1, $transport['retry_strategy']['delay'])
                    ->replaceArgument(2, $transport['retry_strategy']['multiplier'])
                    ->replaceArgument(3, $transport['retry_strategy']['max_delay']);
                $container->setDefinition($retryServiceId, $retryDefinition);

                $transportRetryReferences[$name] = new Reference($retryServiceId);
            }

            if ($transport['rate_limiter']) {
                if (! interface_exists(LimiterInterface::class)) {
                    throw new LogicException(
                        'Rate limiter cannot be used within Messenger as the RateLimiter component is not installed. Try running "composer require symfony/rate-limiter".'
                    );
                }

                $transportRateLimiterReferences[$name] = new Reference('limiter.' . $transport['rate_limiter']);
            }
        }

        $senderReferences = [];
        // alias => service_id
        foreach ($senderAliases as $alias => $serviceId) {
            $senderReferences[$alias] = new Reference($serviceId);
        }
        // service_id => service_id
        foreach ($senderAliases as $serviceId) {
            $senderReferences[$serviceId] = new Reference($serviceId);
        }

        foreach ($config['transports'] as $transport) {
            if (isset($transport['failure_transport'])) {
                if (! isset($senderReferences[$transport['failure_transport']])) {
                    throw new LogicException(sprintf(
                        'Invalid Messenger configuration: the failure transport "%s" is not a valid transport or service id.',
                        $transport['failure_transport']
                    ));
                }
            }
        }

        $failureTransportReferencesByTransportName = array_map(function ($failureTransportName) use (
            $senderReferences
        ) {
            return $senderReferences[$failureTransportName];
        }, $failureTransportsByName);

        $messageToSendersMapping = [];
        foreach ($config['routing'] as $message => $messageConfiguration) {
            if ($message !== '*' && ! class_exists($message) && ! interface_exists($message, false)) {
                throw new LogicException(sprintf(
                    'Invalid Messenger routing configuration: class or interface "%s" not found.',
                    $message
                ));
            }

            // make sure senderAliases contains all senders
            foreach ($messageConfiguration['senders'] as $sender) {
                if (! isset($senderReferences[$sender])) {
                    throw new LogicException(sprintf(
                        'Invalid Messenger routing configuration: the "%s" class is being routed to a sender called "%s". This is not a valid transport or service id.',
                        $message,
                        $sender
                    ));
                }
            }

            $messageToSendersMapping[$message] = $messageConfiguration['senders'];
        }

        $sendersServiceLocator = ServiceLocatorTagPass::register($container, $senderReferences);

        $container->getDefinition('messenger.senders_locator')
            ->replaceArgument(0, $messageToSendersMapping)
            ->replaceArgument(1, $sendersServiceLocator);

        $container->getDefinition('messenger.retry.send_failed_message_for_retry_listener')
            ->replaceArgument(0, $sendersServiceLocator);

        $container->getDefinition('messenger.retry_strategy_locator')
            ->replaceArgument(0, $transportRetryReferences);

        if ($transportRateLimiterReferences === []) {
            $container->removeDefinition('messenger.rate_limiter_locator');
        } else {
            $container->getDefinition('messenger.rate_limiter_locator')
                ->replaceArgument(0, $transportRateLimiterReferences);
        }

        if (\count($failureTransports) > 0) {
            $this->addLoggerArgument($container, 'console.command.messenger_failed_messages_retry', 4);

            $container->getDefinition('console.command.messenger_failed_messages_retry')
                ->addTag('console.command', [
                    'command' => 't3_messenger:failed-messages-retry',
                    'schedulable' => false,
                ])
                ->replaceArgument(0, $config['failure_transport']);

            $container->getDefinition('console.command.messenger_failed_messages_show')
                ->addTag('console.command', [
                    'command' => 't3_messenger:failed-messages-show',
                    'schedulable' => false,
                ])
                ->replaceArgument(0, $config['failure_transport']);

            $container->getDefinition('console.command.messenger_failed_messages_remove')
                ->addTag('console.command', [
                    'command' => 't3_messenger:failed-messages-remove',
                    'schedulable' => false,
                ])
                ->replaceArgument(0, $config['failure_transport']);

            $failureTransportsByTransportNameServiceLocator = ServiceLocatorTagPass::register(
                $container,
                $failureTransportReferencesByTransportName
            );
            $container->getDefinition('messenger.failure.send_failed_message_to_failure_transport_listener')
                ->replaceArgument(0, $failureTransportsByTransportNameServiceLocator);
        } else {
            $container->removeDefinition('messenger.failure.send_failed_message_to_failure_transport_listener');
            $container->removeDefinition('console.command.messenger_failed_messages_retry');
            $container->removeDefinition('console.command.messenger_failed_messages_show');
            $container->removeDefinition('console.command.messenger_failed_messages_remove');
        }

        $this->addLoggerArgument($container, 'console.command.messenger_consume_messages', 3);
        $this->addLoggerArgument($container, 'messenger.retry.send_failed_message_for_retry_listener', 2);
    }

    private function collectMessengerConfigurationsFromPackages(): array
    {
        $coreCache = Bootstrap::createCache('core');
        $packageCache = Bootstrap::createPackageCache($coreCache);
        $packageManager = Bootstrap::createPackageManager(PackageManager::class, $packageCache);

        $config = (new MessengerConfigurationCollector($packageManager))->collect();
        return $this->messengerConfigurationResolver->resolve($config->getArrayCopy());
    }

    /**
     * @param \ReflectionClass<object> $class
     */
    private function getClassChannelName(\ReflectionClass $class): ?string
    {
        // Attribute channel definition is only supported on PHP 8 and later.
        if (class_exists('\ReflectionAttribute', false)) {
            $attributes = $class->getAttributes(Channel::class, \ReflectionAttribute::IS_INSTANCEOF);
            foreach ($attributes as $channel) {
                return $channel->newInstance()
                    ->name;
            }
        }

        if ($class->getParentClass() !== false) {
            return $this->getClassChannelName($class->getParentClass());
        }

        return null;
    }

    private function addLoggerArgument(ContainerBuilder $container, string $id, int $int): void
    {
        $definition = $container->findDefinition($id);

        $channel = $id;
        if ($definition->getClass() !== null) {
            $reflectionClass = $container->getReflectionClass($definition->getClass(), false);
            if ($reflectionClass !== null) {
                $channel = $this->getClassChannelName($reflectionClass) ?? $channel;
            }
        }

        $logger = new Definition(Logger::class);
        $logger->setFactory([new Reference(LogManager::class), 'getLogger']);
        $logger->setArguments([$channel]);
        $logger->setShared(false);

        $definition->replaceArgument($int, $logger);
    }
}
