<?php

declare(strict_types=1);

/*
 * This file is part of the "t3_messenger" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

namespace Ssch\T3Messenger\Tests\Functional\Fixtures\Extensions\t3_messenger_test\Classes\Handlers;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Ssch\T3Messenger\Tests\Functional\Fixtures\Extensions\t3_messenger_test\Classes\Command\MyCommand;
use Ssch\T3Messenger\Tests\Functional\Fixtures\Extensions\t3_messenger_test\Classes\Command\MyFailingCommand;
use Ssch\T3Messenger\Tests\Functional\Fixtures\Extensions\t3_messenger_test\Classes\Command\MyOtherCommand;
use Ssch\T3Messenger\Tests\Functional\Fixtures\Extensions\t3_messenger_test\Classes\Command\MyOtherFailingCommand;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class MyMessengerHandler implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function __invoke(MyOtherCommand $command): void
    {
        $this->logger->info(sprintf('Hi %s', $command->getNote()));
    }

    #[AsMessageHandler]
    public function firstMessageMethod(MyCommand $command): void
    {
        $this->logger->info(sprintf('Hi %s', $command->getEmail()));
    }

    #[AsMessageHandler]
    public function thirdMessageMethod(MyFailingCommand $command): void
    {
        throw new \InvalidArgumentException('Failing by intention');
    }

    #[AsMessageHandler]
    public function fourthMessageMethod(MyOtherFailingCommand $command): void
    {
        throw new \InvalidArgumentException('Failing by intention');
    }
}
