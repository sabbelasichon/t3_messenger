<?php

declare(strict_types=1);

/*
 * This file is part of the "t3_messenger" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

namespace Ssch\T3Messenger\ConfigurationModuleProvider;

use Ssch\T3Messenger\CommandToHandlerMapper;
use Ssch\T3Messenger\DependencyInjection\MessengerConfigurationCollector;
use Ssch\T3Messenger\DependencyInjection\MessengerConfigurationResolver;
use TYPO3\CMS\Core\Package\PackageManager;
use TYPO3\CMS\Lowlevel\ConfigurationModuleProvider\AbstractProvider;

final class MessengerProvider extends AbstractProvider
{
    private MessengerConfigurationResolver $messengerConfigurationResolver;

    private PackageManager $packageManager;

    private CommandToHandlerMapper $commandToHandlerMapper;

    public function __construct(
        MessengerConfigurationResolver $messengerConfigurationResolver,
        PackageManager $packageManager,
        CommandToHandlerMapper $commandToHandlerMapper
    ) {
        $this->messengerConfigurationResolver = $messengerConfigurationResolver;
        $this->packageManager = $packageManager;
        $this->commandToHandlerMapper = $commandToHandlerMapper;
    }

    public function getConfiguration(): array
    {
        $config = (new MessengerConfigurationCollector($this->packageManager))->collect();

        return [
            'Messenger Configuration' => $this->messengerConfigurationResolver->resolve($config->getArrayCopy()),
            'Messenger Mapping' => $this->commandToHandlerMapper->commandToHandlerMapping(),
        ];
    }
}
