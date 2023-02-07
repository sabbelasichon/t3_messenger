<?php

declare(strict_types=1);

/*
 * This file is part of the "t3_messenger" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

namespace Ssch\T3Messenger\Mailer\Event;

use TYPO3\CMS\Core\Mail\MailerInterface;

final class AfterMailerSentMessageEvent
{
    private MailerInterface $mailer;

    public function __construct(MailerInterface $mailer)
    {
        $this->mailer = $mailer;
    }

    public function getMailer(): MailerInterface
    {
        return $this->mailer;
    }
}
