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
use Symfony\Component\Messenger\Handler\MessageSubscriberInterface;

final class MyMessengerHandler implements MessageSubscriberInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function __invoke(MyOtherCommand $command): void
    {
        $this->logger->info(sprintf('Hi %s', $command->getNote()));
    }

    public function firstMessageMethod(MyCommand $command): void
    {
        $this->logger->info(sprintf('Hi %s', $command->getEmail()));
    }

    public function secondMessageMethod(MyOtherCommand $command): void
    {
        $this->logger->info(sprintf('Hi %s', $command->getNote()));
    }

    public function thirdMessageMethod(MyFailingCommand $command): void
    {
        throw new \InvalidArgumentException('Failing by intention');
    }

    public static function getHandledMessages(): iterable
    {
        yield MyCommand::class => [
            'method' => 'firstMessageMethod',
        ];

        yield MyOtherCommand::class => [
            'method' => 'secondMessageMethod',
        ];

        yield MyFailingCommand::class => [
            'method' => 'thirdMessageMethod',
        ];
    }
}
