<?php

declare(strict_types=1);

/*
 * This file is part of the "t3_messenger" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

namespace Ssch\T3Messenger\Tests\Functional\Fixtures\Extensions\t3_messenger_test\Classes\Service;

use Ssch\T3Messenger\Tests\Functional\Fixtures\Extensions\t3_messenger_test\Classes\Command\MyCommand;
use Symfony\Component\Messenger\MessageBusInterface;

final class MyService
{
    private MessageBusInterface $messageBus;

    public function __construct(MessageBusInterface $messageBus)
    {
        $this->messageBus = $messageBus;
    }

    public function dispatch(): void
    {
        $this->messageBus->dispatch(new MyCommand('max.mustermann@domain.com'));
    }
}
