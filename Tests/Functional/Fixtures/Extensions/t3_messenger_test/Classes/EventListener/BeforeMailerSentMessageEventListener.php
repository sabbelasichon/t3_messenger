<?php

declare(strict_types=1);

/*
 * This file is part of the "t3_messenger" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

namespace Ssch\T3Messenger\Tests\Functional\Fixtures\Extensions\t3_messenger_test\Classes\EventListener;

use Symfony\Component\Mime\Email;
use TYPO3\CMS\Core\Mail\Event\BeforeMailerSentMessageEvent;

final class BeforeMailerSentMessageEventListener
{
    public function __invoke(BeforeMailerSentMessageEvent $event): void
    {
        $message = $event->getMessage();
        if (! $message instanceof Email) {
            return;
        }

        $message->subject('This is modified by an event');
        $event->setMessage($message);
    }
}
