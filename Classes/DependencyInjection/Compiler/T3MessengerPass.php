<?php

declare(strict_types=1);

/*
 * This file is part of the "t3_tactician" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

namespace Ssch\T3Messenger\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\ServiceLocatorTagPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\MessageBusInterface;
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

        //        if (!interface_exists(DenormalizerInterface::class)) {
        //            $container->removeDefinition('serializer.normalizer.flatten_exception');
        //        }

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

        $senderReferences = [];
        $messageToSendersMapping = [];
        $sendersServiceLocator = ServiceLocatorTagPass::register($container, $senderReferences);

        $container->getDefinition('messenger.senders_locator')
            ->replaceArgument(0, $messageToSendersMapping)
            ->replaceArgument(1, $sendersServiceLocator)
        ;
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
