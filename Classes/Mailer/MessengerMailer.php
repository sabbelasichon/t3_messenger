<?php

declare(strict_types=1);

/*
 * This file is part of the "t3_messenger" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

namespace Ssch\T3Messenger\Mailer;

use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\MailerInterface as SymfonyMailerInterface;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\RawMessage;
use TYPO3\CMS\Core\Mail\Event\AfterMailerInitializationEvent;
use TYPO3\CMS\Core\Mail\Event\AfterMailerSentMessageEvent;
use TYPO3\CMS\Core\Mail\Event\BeforeMailerSentMessageEvent;
use TYPO3\CMS\Core\Mail\MailerInterface;

final class MessengerMailer implements MailerInterface
{
    private SymfonyMailerInterface $mailer;

    private MailValidityResolver $mailValidityResolver;

    private EventDispatcherInterface $eventDispatcher;

    private TransportInterface $transport;

    private TransportInterface $realTransport;

    public function __construct(
        SymfonyMailerInterface $mailer,
        MailValidityResolver $mailValidityResolver,
        EventDispatcherInterface $eventDispatcher,
        TransportInterface $transport,
        TransportInterface $realTransport
    ) {
        $this->mailer = $mailer;
        $this->mailValidityResolver = $mailValidityResolver;
        $this->eventDispatcher = $eventDispatcher;
        $eventDispatcher->dispatch(new AfterMailerInitializationEvent($this));
        $this->transport = $transport;
        $this->realTransport = $realTransport;
    }

    public function send(RawMessage $message, Envelope $envelope = null): void
    {
        $this->mailValidityResolver->resolve($message);

        // After static enrichment took place, allow listeners to further manipulate message and envelope
        $event = new BeforeMailerSentMessageEvent($this, $message, $envelope);
        $this->eventDispatcher->dispatch($event);

        $this->mailer->send($message, $envelope);

        // Finally, allow further processing by listeners after the message has been sent
        $this->eventDispatcher->dispatch(new AfterMailerSentMessageEvent($this));
    }

    public function getSentMessage(): ?SentMessage
    {
        return null;
    }

    public function getTransport(): TransportInterface
    {
        return $this->transport;
    }

    public function getRealTransport(): TransportInterface
    {
        return $this->realTransport;
    }
}
