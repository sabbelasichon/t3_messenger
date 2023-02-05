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
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\RawMessage;
use TYPO3\CMS\Core\Utility\MailUtility;

final class MessengerMailer implements MailerInterface
{
    private MailerInterface $mailer;

    public function __construct(MailerInterface $mailer)
    {
        $this->mailer = $mailer;
    }

    public function send(RawMessage $message, Envelope $envelope = null): void
    {
        if ($message instanceof Email) {
            // Ensure to always have a From: header set
            if ($message->getFrom() === []) {
                $address = MailUtility::getSystemFromAddress();
                if ($address !== '') {
                    $name = MailUtility::getSystemFromName();
                    if ($name !== null) {
                        $from = new Address($address, $name);
                    } else {
                        $from = new Address($address);
                    }
                    $message->from($from);
                }
            }
            if ($message->getReplyTo() === []) {
                $replyTo = MailUtility::getSystemReplyTo();
                if ($replyTo !== []) {
                    $address = key($replyTo);
                    if ($address === 0) {
                        $replyTo = new Address($replyTo[$address]);
                    } else {
                        $replyTo = new Address((string) $address, reset($replyTo));
                    }
                    $message->replyTo($replyTo);
                }
            }
            $message->getHeaders()
                ->addTextHeader('X-Mailer', 'TYPO3');
        }

        $this->mailer->send($message, $envelope);
    }
}
