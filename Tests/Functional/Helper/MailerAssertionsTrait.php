<?php

declare(strict_types=1);

/*
 * This file is part of the "t3_messenger" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

namespace Ssch\T3Messenger\Tests\Functional\Helper;

use Symfony\Component\Mailer\Event\MessageEvents;
use Symfony\Component\Mailer\Test\Constraint as MailerConstraint;
use Symfony\Component\Mime\RawMessage;
use Symfony\Component\Mime\Test\Constraint as MimeConstraint;

trait MailerAssertionsTrait
{
    public function assertQueuedEmailCount(int $count, string $transport = null, string $message = ''): void
    {
        self::assertThat(
            $this->getMessageMailerEvents(),
            new MailerConstraint\EmailCount($count, $transport, true),
            $message
        );
    }

    public function assertEmailHtmlBodyContains(RawMessage $email, string $text, string $message = ''): void
    {
        self::assertThat($email, new MimeConstraint\EmailHtmlBodyContains($text), $message);
    }

    public function assertEmailHasHeader(RawMessage $email, string $headerName, string $message = ''): void
    {
        self::assertThat($email, new MimeConstraint\EmailHasHeader($headerName), $message);
    }

    /**
     * @return RawMessage[]
     */
    public function getMailerMessages(string $transport = null): array
    {
        return $this->getMessageMailerEvents()
            ->getMessages($transport);
    }

    public function getMailerMessage(int $index = 0, string $transport = null): ?RawMessage
    {
        return $this->getMailerMessages($transport)[$index] ?? null;
    }

    private function getMessageMailerEvents(): MessageEvents
    {
        return $this->get('mailer.logger_message_listener')
            ->getEvents();
    }
}
