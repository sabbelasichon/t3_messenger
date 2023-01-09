<?php

declare(strict_types=1);

/*
 * This file is part of the "t3_tactician" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

namespace Ssch\T3Messenger\Handlers;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Ssch\T3Messenger\Command\MyCommand;
use Symfony\Component\Messenger\Handler\MessageSubscriberInterface;

final class MyMessengerHandler implements MessageSubscriberInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function firstMessageMethod(MyCommand $command): void
    {
        echo 'test';
    }

    public static function getHandledMessages(): iterable
    {
        yield MyCommand::class => [
            'method' => 'firstMessageMethod',
        ];
    }
}
