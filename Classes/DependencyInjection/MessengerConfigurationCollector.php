<?php

declare(strict_types=1);

/*
 * This file is part of the "t3_messenger" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

namespace Ssch\T3Messenger\DependencyInjection;

use Ssch\T3Messenger\Domain\Dto\MessengerConfiguration;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Package\PackageManager;

final class MessengerConfigurationCollector
{
    private PackageManager $packageManager;

    public function __construct(PackageManager $packageManager)
    {
        $this->packageManager = $packageManager;
    }

    public function collect(): MessengerConfiguration
    {
        $configPackages = ['Configuration/Messenger.php'];
        if (Environment::getContext()->isDevelopment()) {
            $configPackages[] = 'Configuration/dev/Messenger.php';
        } elseif (Environment::getContext()->isTesting()) {
            $configPackages[] = 'Configuration/test/Messenger.php';
        }

        $config = new MessengerConfiguration();
        foreach ($this->packageManager->getAvailablePackages() as $package) {
            foreach ($configPackages as $configPackage) {
                $commandBusConfigurationFile = $package->getPackagePath() . $configPackage;
                if (! file_exists($commandBusConfigurationFile)) {
                    continue;
                }

                $commandBusInPackage = require $commandBusConfigurationFile;
                if (! is_array($commandBusInPackage)) {
                    continue;
                }

                $config->exchangeArray(array_replace_recursive($config->getArrayCopy(), $commandBusInPackage));
                $config->addExtension($package->getPackageKey());
            }
        }

        return $config;
    }
}
