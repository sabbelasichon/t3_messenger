<?php

declare(strict_types=1);

/*
 * This file is part of the "t3_messenger" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

namespace Ssch\T3Messenger\Mailer;

use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\RawMessage;

final class MessengerMailer implements MailerInterface
{
    private MailerInterface $mailer;

    private MailValidityResolver $mailValidityResolver;

    public function __construct(MailerInterface $mailer, MailValidityResolver $mailValidityResolver)
    {
        $this->mailer = $mailer;
        $this->mailValidityResolver = $mailValidityResolver;
    }

    public function send(RawMessage $message, Envelope $envelope = null): void
    {
        $this->mailValidityResolver->resolve($message);
        $this->mailer->send($message, $envelope);
    }
}
