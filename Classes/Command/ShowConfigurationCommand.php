<?php

declare(strict_types=1);

/*
 * This file is part of the "t3_messenger" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

namespace Ssch\T3Messenger\Command;

use Ssch\T3Messenger\DependencyInjection\MessengerConfigurationCollector;
use Ssch\T3Messenger\DependencyInjection\MessengerConfigurationResolver;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\CMS\Core\Package\PackageManager;

final class ShowConfigurationCommand extends Command
{
    protected static $defaultName = 'Show Messenger configuration';

    protected static $defaultDescription = 'Show global Messenger configuration combined from all extensions';

    private MessengerConfigurationResolver $messengerConfigurationResolver;

    private PackageManager $packageManager;

    public function __construct(
        MessengerConfigurationResolver $messengerConfigurationResolver,
        PackageManager $packageManager
    ) {
        parent::__construct();
        $this->messengerConfigurationResolver = $messengerConfigurationResolver;
        $this->packageManager = $packageManager;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $config = (new MessengerConfigurationCollector($this->packageManager))->collect();
        $messengerConfiguration = $this->messengerConfigurationResolver->resolve($config->getArrayCopy());

        foreach ($config->getExtensions() as $extension) {
            $output->writeln('Found configuration in extension ' . $extension);
        }

        $output->writeln(var_export($messengerConfiguration, true));

        return Command::SUCCESS;
    }
}
