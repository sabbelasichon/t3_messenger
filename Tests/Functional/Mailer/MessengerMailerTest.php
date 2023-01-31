<?php

declare(strict_types=1);

/*
 * This file is part of the "t3_messenger" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

namespace Ssch\T3Messenger\Tests\Functional\Mailer;

use Symfony\Component\Mailer\Event\MessageEvents;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mailer\Test\Constraint\EmailCount;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Test\Constraint\EmailHasHeader;
use TYPO3\CMS\Core\Mail\MailMessage;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

final class MessengerMailerTest extends FunctionalTestCase
{
    protected $initializeDatabase = false;

    protected $testExtensionsToLoad = [
        'typo3conf/ext/typo3_psr_cache_adapter',
        'typo3conf/ext/t3_messenger',
        'typo3conf/ext/t3_messenger/Tests/Functional/Fixtures/Extensions/t3_messenger_test',
    ];

    protected $configurationToUseInTestInstance = [
        'MAIL' => [
            'transport' => 'null',
            'defaultMailFromAddress' => 'info@mustermann.com',
            'defaultMailFromName' => 'Mustermann AG',
            'defaultMailReplyToAddress' => 'info@mustermann.com',
            'defaultMailReplyToName' => 'Mustermann AG',
        ],
    ];

    private MailerInterface $subject;

    protected function setUp(): void
    {
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
        self::assertEquals([new Address('info@mustermann.com', 'Mustermann AG')], $mailMessage->getFrom());
        self::assertEquals([new Address('info@mustermann.com', 'Mustermann AG')], $mailMessage->getReplyTo());
        self::assertThat($this->getMessageMailerEvents(), new EmailCount(1, 'null://', true));
        self::assertThat($mailMessage, new EmailHasHeader('X-Mailer'));
    }

    private function getMessageMailerEvents(): MessageEvents
    {
        return $this->get('mailer.logger_message_listener')
            ->getEvents();
    }
}
