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
use Symfony\Component\Messenger\Handler\MessageSubscriberInterface;

final class MyMessengerHandler implements MessageSubscriberInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function firstMessageMethod(MyCommand $command): void
    {
        $this->logger->critical(sprintf('Hi %s', $command->getEmail()));
    }

    public static function getHandledMessages(): iterable
    {
        yield MyCommand::class => [
            'method' => 'firstMessageMethod',
        ];
    }
}
