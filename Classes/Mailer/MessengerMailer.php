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
use Symfony\Component\Mailer\Event\MessageEvent;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\Messenger\SendEmailMessage;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Mime\RawMessage;
use TYPO3\CMS\Core\Mail\Event\AfterMailerInitializationEvent;
use TYPO3\CMS\Core\Mail\Event\AfterMailerSentMessageEvent;
use TYPO3\CMS\Core\Mail\Event\BeforeMailerSentMessageEvent;
use TYPO3\CMS\Core\Mail\MailerInterface;

final class MessengerMailer implements MailerInterface
{
    private MailValidityResolver $mailValidityResolver;

    private EventDispatcherInterface $eventDispatcher;

    private TransportInterface $transport;

    private TransportInterface $realTransport;

    private MessageBusInterface $bus;

    private ?SentMessage $sentMessage;

    public function __construct(
        MessageBusInterface $bus,
        MailValidityResolver $mailValidityResolver,
        EventDispatcherInterface $eventDispatcher,
        TransportInterface $transport,
        TransportInterface $realTransport
    ) {
        $this->mailValidityResolver = $mailValidityResolver;
        $this->eventDispatcher = $eventDispatcher;
        $eventDispatcher->dispatch(new AfterMailerInitializationEvent($this));
        $this->transport = $transport;
        $this->realTransport = $realTransport;
        $this->bus = $bus;
    }

    public function send(RawMessage $message, Envelope $envelope = null): void
    {
        $this->mailValidityResolver->resolve($message);

        // After static enrichment took place, allow listeners to further manipulate message and envelope
        $event = new BeforeMailerSentMessageEvent($this, $message, $envelope);
        $this->eventDispatcher->dispatch($event);

        // The dispatched event here has `queued` set to `true`; the goal is NOT to render the message, but to let
        // listeners do something before a message is sent to the queue.
        // We are using a cloned message as we still want to dispatch the **original** message, not the one modified by listeners.
        // That's because the listeners will run again when the email is sent via Messenger by the transport (see `AbstractTransport`).
        // Listeners should act depending on the `$queued` argument of the `MessageEvent` instance.
        $clonedMessage = clone $message;
        $clonedEnvelope = $envelope !== null ? clone $envelope : Envelope::create($clonedMessage);
        $event = new MessageEvent($clonedMessage, $clonedEnvelope, (string) $this->transport, true);
        $this->eventDispatcher->dispatch($event);

        try {
            $envelope = $this->bus->dispatch(new SendEmailMessage($message, $envelope));
            $handledStamp = $envelope->last(HandledStamp::class);

            if ($handledStamp instanceof HandledStamp) {
                $this->sentMessage = $handledStamp->getResult();
            }
        } catch (HandlerFailedException $e) {
            foreach ($e->getNestedExceptions() as $nested) {
                if ($nested instanceof TransportExceptionInterface) {
                    throw $nested;
                }
            }
            throw $e;
        }

        // Finally, allow further processing by listeners after the message has been sent
        $this->eventDispatcher->dispatch(new AfterMailerSentMessageEvent($this));
    }

    public function getSentMessage(): ?SentMessage
    {
        return $this->sentMessage;
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
