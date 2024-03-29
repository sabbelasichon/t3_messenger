<?php

declare(strict_types=1);

/*
 * This file is part of the "t3_messenger" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

namespace Ssch\T3Messenger\Tests\Functional\Mailer;

use Ssch\T3Messenger\Tests\Functional\Helper\MailerAssertionsTrait;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mime\Address;
use TYPO3\CMS\Core\Mail\MailerInterface;
use TYPO3\CMS\Core\Mail\MailMessage;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

final class MessengerMailerTest extends FunctionalTestCase
{
    use MailerAssertionsTrait;

    private MailerInterface $subject;

    protected function setUp(): void
    {
        $this->initializeDatabase = false;
        $this->configurationToUseInTestInstance = [
            'MAIL' => [
                'transport' => 'null',
                'defaultMailFromAddress' => 'info@mustermann.com',
                'defaultMailFromName' => 'Mustermann AG',
                'defaultMailReplyToAddress' => 'info@mustermann.com',
                'defaultMailReplyToName' => 'Mustermann AG',
            ],
        ];
        $this->testExtensionsToLoad = [
            'typo3conf/ext/typo3_psr_cache_adapter',
            'typo3conf/ext/t3_messenger',
            'typo3conf/ext/t3_messenger/Tests/Functional/Fixtures/Extensions/t3_messenger_test',
        ];

        parent::setUp();
        $this->subject = $this->get(MailerInterface::class);
    }

    public function testThatEmailIstSentViaMessenger(): void
    {
        $mailMessage = GeneralUtility::makeInstance(MailMessage::class);
        $mailMessage
            ->subject('Test')
            ->text('Hello World')
            ->to('info@test.de');

        $this->subject->send($mailMessage);
        self::assertInstanceOf(SentMessage::class, $this->subject->getSentMessage());
        self::assertEquals([new Address('info@mustermann.com', 'Mustermann AG')], $mailMessage->getFrom());
        self::assertEquals([new Address('info@mustermann.com', 'Mustermann AG')], $mailMessage->getReplyTo());

        $this->assertQueuedEmailCount(1, 'null://');
        $this->assertEmailHasHeader($mailMessage, 'X-Mailer');
        self::assertSame('This is modified by an event', $mailMessage->getSubject());
        self::assertEquals($mailMessage, $this->subject->getSentMessage()->getOriginalMessage());
    }
}
