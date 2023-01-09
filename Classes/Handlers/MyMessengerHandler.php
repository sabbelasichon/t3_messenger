<?php

declare(strict_types=1);

/*
 * This file is part of the "t3_tactician" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

namespace Ssch\T3Messenger\Handlers;

use Ssch\T3Messenger\Command\MyCommand;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;

final class MyMessengerHandler implements MessageHandlerInterface
{
    public function __invoke(MyCommand $command): void
    {
    }
}
