<?php

declare(strict_types=1);

/*
 * This file is part of the "t3_messenger" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

namespace Ssch\T3Messenger\Mailer\Event;

use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mime\RawMessage;
use TYPO3\CMS\Core\Mail\MailerInterface;

final class BeforeMailerSentMessageEvent
{
    private MailerInterface $mailer;

    private RawMessage $message;

    private ?Envelope $envelope;

    public function __construct(MailerInterface $mailer, RawMessage $message, ?Envelope $envelope = null)
    {
        $this->mailer = $mailer;
        $this->message = $message;
        $this->envelope = $envelope;
    }

    public function getMailer(): MailerInterface
    {
        return $this->mailer;
    }

    public function getMessage(): RawMessage
    {
        return $this->message;
    }

    public function getEnvelope(): ?Envelope
    {
        return $this->envelope;
    }

    public function setMessage(RawMessage $message): void
    {
        $this->message = $message;
    }

    public function setEnvelope(?Envelope $envelope = null): void
    {
        $this->envelope = $envelope;
    }
}
