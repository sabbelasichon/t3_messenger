<?php

declare(strict_types=1);

/*
 * This file is part of the "t3_messenger" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

namespace Ssch\T3Messenger\Tests\Unit\Mailer;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Ssch\T3Messenger\Mailer\MailValidityResolver;
use Ssch\T3Messenger\Mailer\MessengerMailer;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Event\MessageEvent;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\Messenger\SendEmailMessage;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Messenger\Envelope as MessengerEnvelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use TYPO3\CMS\Core\Mail\Event\AfterMailerSentMessageEvent;
use TYPO3\CMS\Core\Mail\Event\BeforeMailerSentMessageEvent;
use TYPO3\CMS\Core\Mail\MailerInterface;
use TYPO3\CMS\Core\Mail\MailMessage;

final class MessengerMailerTest extends TestCase
{
    /**
     * @return iterable<string, array{bool, bool, string}>
     */
    public static function provideEventChanges(): iterable
    {
        yield 'unchanged message without envelope' => [false, false, 'keep'];
        yield 'unchanged message with envelope' => [true, false, 'keep'];
        yield 'replaced message without envelope' => [false, true, 'keep'];
        yield 'replaced message with envelope' => [true, true, 'keep'];
        yield 'added envelope' => [false, false, 'replace'];
        yield 'replaced envelope' => [true, false, 'replace'];
        yield 'replaced message and envelope' => [true, true, 'replace'];
        yield 'removed envelope' => [true, false, 'clear'];
    }

    #[DataProvider('provideEventChanges')]
    public function testSendRespectsBeforeMailerSentMessageEvent(
        bool $hasEnvelope,
        bool $replaceMessage,
        string $envelopeChange
    ): void {
        $message = $this->createEmail('Original message', 'original@example.org');
        $envelope = $hasEnvelope
            ? new Envelope(new Address('sender@example.org'), [new Address('recipient@example.org')])
            : null;
        $expectedMessage = $replaceMessage
            ? $this->createEmail('Replacement message', 'replacement@example.org')
            : $message;
        $expectedEnvelope = match ($envelopeChange) {
            'replace' => new Envelope(
                new Address('replacement-sender@example.org'),
                [new Address('replacement-recipient@example.org')]
            ),
            'clear' => null,
            default => $envelope,
        };
        $queuedEventDispatched = false;

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects(self::exactly(4))->method('dispatch')->willReturnCallback(
            static function (object $event) use (
                $expectedMessage,
                $expectedEnvelope,
                &$queuedEventDispatched
            ): object {
                if ($event instanceof BeforeMailerSentMessageEvent) {
                    $event->setMessage($expectedMessage);
                    $event->setEnvelope($expectedEnvelope);
                }
                if ($event instanceof MessageEvent) {
                    $queuedEventDispatched = true;
                    self::assertTrue($event->isQueued());
                    self::assertEquals($expectedMessage, $event->getMessage());
                    self::assertNotSame($expectedMessage, $event->getMessage());
                    self::assertEquals(
                        $expectedEnvelope ?? Envelope::create($expectedMessage),
                        $event->getEnvelope()
                    );
                    if ($expectedEnvelope !== null) {
                        self::assertNotSame($expectedEnvelope, $event->getEnvelope());
                    }
                }

                return $event;
            }
        );

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::once())->method('dispatch')->willReturnCallback(
            static function (object $queuedMessage) use ($expectedMessage, $expectedEnvelope): MessengerEnvelope {
                self::assertInstanceOf(SendEmailMessage::class, $queuedMessage);
                self::assertSame($expectedMessage, $queuedMessage->getMessage());
                self::assertSame($expectedEnvelope, $queuedMessage->getEnvelope());

                return new MessengerEnvelope($queuedMessage);
            }
        );
        $transport = $this->createMock(TransportInterface::class);
        $transport->expects(self::once())->method('__toString')->willReturn('null://');
        $subject = new MessengerMailer(
            $bus,
            new MailValidityResolver(),
            $eventDispatcher,
            $transport,
            $transport
        );

        $subject->send($message, $envelope);

        self::assertTrue($queuedEventDispatched);
    }

    public function testSentMessageIsNullBeforeSending(): void
    {
        $subject = $this->createMailer($this->createStub(MessageBusInterface::class));

        self::assertNull($subject->getSentMessage());
    }

    public function testQueuedSendHasNoSentMessageIncludingInAfterEvent(): void
    {
        $message = $this->createEmail('Queued message', 'recipient@example.org');
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::once())->method('dispatch')->willReturn(new MessengerEnvelope(new SendEmailMessage($message)));
        $afterEventDispatched = false;
        $eventDispatcher = $this->createStub(EventDispatcherInterface::class);
        $eventDispatcher->method('dispatch')->willReturnCallback(
            static function (object $event) use (&$afterEventDispatched): object {
                if ($event instanceof AfterMailerSentMessageEvent) {
                    $afterEventDispatched = true;
                    $mailer = $event->getMailer();
                    self::assertInstanceOf(MailerInterface::class, $mailer);
                    self::assertNull($mailer->getSentMessage());
                }

                return $event;
            }
        );
        $subject = $this->createMailer($bus, $eventDispatcher);

        $subject->send($message);

        self::assertTrue($afterEventDispatched);
        self::assertNull($subject->getSentMessage());
    }

    public function testSynchronousSendReturnsHandledResult(): void
    {
        $message = $this->createEmail('Synchronous message', 'recipient@example.org');
        $sentMessage = new SentMessage($message, Envelope::create($message));
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::once())->method('dispatch')->willReturn(
            new MessengerEnvelope(new SendEmailMessage($message), [new HandledStamp($sentMessage, 'mailer')])
        );
        $subject = $this->createMailer($bus);

        $subject->send($message);

        self::assertSame($sentMessage, $subject->getSentMessage());
    }

    public function testQueuedSendClearsPreviousSynchronousResult(): void
    {
        $message = $this->createEmail('Repeated message', 'recipient@example.org');
        $sentMessage = new SentMessage($message, Envelope::create($message));
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::exactly(2))->method('dispatch')->willReturnOnConsecutiveCalls(
            new MessengerEnvelope(new SendEmailMessage($message), [new HandledStamp($sentMessage, 'mailer')]),
            new MessengerEnvelope(new SendEmailMessage($message))
        );
        $subject = $this->createMailer($bus);
        $subject->send($message);
        self::assertSame($sentMessage, $subject->getSentMessage());

        $subject->send($message);

        self::assertNull($subject->getSentMessage());
    }

    public function testFailedSendClearsPreviousResultAndPropagatesTransportException(): void
    {
        $message = $this->createEmail('Repeated message', 'recipient@example.org');
        $sentMessage = new SentMessage($message, Envelope::create($message));
        $failure = new TransportException('Transport failed');
        $dispatchCalls = 0;
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::exactly(2))->method('dispatch')->willReturnCallback(
            static function (object $queuedMessage) use ($sentMessage, $failure, &$dispatchCalls): MessengerEnvelope {
                $envelope = new MessengerEnvelope($queuedMessage);
                if (++$dispatchCalls === 1) {
                    return $envelope->with(new HandledStamp($sentMessage, 'mailer'));
                }

                throw new HandlerFailedException($envelope, [$failure]);
            }
        );
        $subject = $this->createMailer($bus);
        $subject->send($message);
        self::assertSame($sentMessage, $subject->getSentMessage());

        try {
            $subject->send($message);
            self::fail('The transport exception must propagate.');
        } catch (TransportException $exception) {
            self::assertSame($failure, $exception);
            self::assertNull($subject->getSentMessage());
        }
    }

    public function testMailMessageSendQueuesWithoutReportingSynchronousDelivery(): void
    {
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::once())->method('dispatch')->willReturnCallback(
            static function (object $queuedMessage): MessengerEnvelope {
                self::assertInstanceOf(SendEmailMessage::class, $queuedMessage);
                $email = $queuedMessage->getMessage();
                self::assertInstanceOf(Email::class, $email);
                self::assertSame('Verification', $email->getSubject());

                return new MessengerEnvelope($queuedMessage);
            }
        );
        $message = new class($this->createMailer($bus)) extends MailMessage {
            public function __construct(MailerInterface $mailer)
            {
                parent::__construct();
                $this->mailer = $mailer;
            }
        };
        $message->from('sender@example.org')
            ->replyTo('reply@example.org')
            ->to('recipient@example.org')
            ->subject('Verification')
            ->text('Verification link');

        self::assertFalse($message->send());
        self::assertFalse($message->isSent());
    }

    private function createMailer(
        MessageBusInterface $bus,
        ?EventDispatcherInterface $eventDispatcher = null
    ): MessengerMailer {
        if ($eventDispatcher === null) {
            $eventDispatcher = $this->createStub(EventDispatcherInterface::class);
            $eventDispatcher->method('dispatch')->willReturnArgument(0);
        }
        $transport = $this->createStub(TransportInterface::class);
        $transport->method('__toString')->willReturn('null://');

        return new MessengerMailer($bus, new MailValidityResolver(), $eventDispatcher, $transport, $transport);
    }

    private function createEmail(string $subject, string $recipient): Email
    {
        return (new Email())
            ->from('sender@example.org')
            ->replyTo('reply@example.org')
            ->to($recipient)
            ->subject($subject)
            ->text('Message body');
    }
}
