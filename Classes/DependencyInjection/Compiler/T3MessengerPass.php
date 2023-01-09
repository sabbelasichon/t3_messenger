<?php

declare(strict_types=1);

/*
 * This file is part of the "t3_tactician" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

namespace Ssch\T3Messenger\DependencyInjection\Compiler;

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
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use TYPO3\CMS\Core\Core\Bootstrap;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Package\PackageManager;
use TYPO3\CMS\Core\Utility\GeneralUtility;

final class T3MessengerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        if (! interface_exists(MessageBusInterface::class)) {
            throw new LogicException(
                'Messenger support cannot be enabled as the Messenger component is not installed. Try running "composer require symfony/messenger".'
            );
        }

        $config = $this->createCommandBusConfigurationFromPackages();

        if ($config->count() === 0) {
            return;
        }

        if (! interface_exists(DenormalizerInterface::class)) {
            $container->removeDefinition('serializer.normalizer.flatten_exception');
        }

        if (ContainerBuilder::willBeAvailable(
            'symfony/amqp-messenger',
            AmqpTransportFactory::class,
            ['symfony/framework-bundle', 'symfony/messenger']
        )) {
            $container->getDefinition('messenger.transport.amqp.factory')
                ->addTag('messenger.transport_factory');
        }

        if (ContainerBuilder::willBeAvailable(
            'symfony/redis-messenger',
            RedisTransportFactory::class,
            ['symfony/framework-bundle', 'symfony/messenger']
        )) {
            $container->getDefinition('messenger.transport.redis.factory')
                ->addTag('messenger.transport_factory');
        }

        if (ContainerBuilder::willBeAvailable(
            'symfony/amazon-sqs-messenger',
            AmazonSqsTransportFactory::class,
            ['symfony/framework-bundle', 'symfony/messenger']
        )) {
            $container->getDefinition('messenger.transport.sqs.factory')
                ->addTag('messenger.transport_factory');
        }

        if (ContainerBuilder::willBeAvailable(
            'symfony/beanstalkd-messenger',
            BeanstalkdTransportFactory::class,
            ['symfony/framework-bundle', 'symfony/messenger']
        )) {
            $container->getDefinition('messenger.transport.beanstalkd.factory')
                ->addTag('messenger.transport_factory');
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
            $middleware = $bus['middleware'] ?? [];
            $enableDefaultMiddleware = $bus['default_middleware']['enabled'] ?? true;

            if ($enableDefaultMiddleware) {
                if ($enableDefaultMiddleware === 'allow_no_handlers') {
                    $defaultMiddleware['after'][1]['arguments'] = [true];
                } else {
                    unset($defaultMiddleware['after'][1]['arguments']);
                }

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

        if (empty($config['transports'])) {
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
            if ($transport['failure_transport']) {
                $failureTransports[] = $transport['failure_transport'];
                $failureTransportsByName[$name] = $transport['failure_transport'];
            } elseif ($config['failure_transport']) {
                $failureTransportsByName[$name] = $config['failure_transport'];
            }
        }

        $senderAliases = [];
        $transportRetryReferences = [];
        foreach ($config['transports'] as $name => $transport) {
            $serializerId = $transport['serializer'] ?? 'messenger.default_serializer';
            $transportDefinition = (new Definition(TransportInterface::class))
                ->setFactory([new Reference('messenger.transport_factory'), 'createTransport'])
                ->setArguments([$transport['dsn'], $transport['options'] + [
                    'transport_name' => $name,
                ], new Reference($serializerId)])
                ->addTag(
                    'messenger.receiver',
                    [
                        'alias' => $name,
                        'is_failure_transport' => \in_array($name, $failureTransports, true),
                    ]
                )
            ;
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

        foreach ($config['transports'] as $name => $transport) {
            if ($transport['failure_transport']) {
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
            ->replaceArgument(1, $sendersServiceLocator)
        ;

        //        $container->getDefinition('messenger.retry.send_failed_message_for_retry_listener')
        //                  ->replaceArgument(0, $sendersServiceLocator)
        //        ;

        $container->getDefinition('messenger.retry_strategy_locator')
            ->replaceArgument(0, $transportRetryReferences);

        if (\count($failureTransports) > 0) {
            $container->getDefinition('console.command.messenger_failed_messages_retry')
                ->replaceArgument(0, $config['failure_transport']);
            $container->getDefinition('console.command.messenger_failed_messages_show')
                ->replaceArgument(0, $config['failure_transport']);
            $container->getDefinition('console.command.messenger_failed_messages_remove')
                ->replaceArgument(0, $config['failure_transport']);

            $failureTransportsByTransportNameServiceLocator = ServiceLocatorTagPass::register(
                $container,
                $failureTransportReferencesByTransportName
            );
        //            $container->getDefinition('messenger.failure.send_failed_message_to_failure_transport_listener')
        //                      ->replaceArgument(0, $failureTransportsByTransportNameServiceLocator);
        } else {
            //            $container->removeDefinition('messenger.failure.send_failed_message_to_failure_transport_listener');
            $container->removeDefinition('console.command.messenger_failed_messages_retry');
            $container->removeDefinition('console.command.messenger_failed_messages_show');
            $container->removeDefinition('console.command.messenger_failed_messages_remove');
        }
    }

    private function createCommandBusConfigurationFromPackages(): \ArrayObject
    {
        $versionInformation = GeneralUtility::makeInstance(Typo3Version::class);
        if ($versionInformation->getMajorVersion() >= 11) {
            $coreCache = Bootstrap::createCache('core');
            $packageCache = Bootstrap::createPackageCache($coreCache);
            $packageManager = Bootstrap::createPackageManager(PackageManager::class, $packageCache);
        } else {
            $coreCache = Bootstrap::createCache('core');
            $packageManager = Bootstrap::createPackageManager(PackageManager::class, $coreCache);
        }

        $config = new \ArrayObject();
        foreach ($packageManager->getAvailablePackages() as $package) {
            $commandBusConfigurationFile = $package->getPackagePath() . 'Configuration/Messenger.php';
            if (file_exists($commandBusConfigurationFile)) {
                $commandBusInPackage = require $commandBusConfigurationFile;
                if (is_array($commandBusInPackage)) {
                    $config->exchangeArray(array_replace_recursive($config->getArrayCopy(), $commandBusInPackage));
                }
            }
        }

        return $config;
    }
}
