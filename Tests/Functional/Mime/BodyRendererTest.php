<?php

declare(strict_types=1);

/*
 * This file is part of the "t3_messenger" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

namespace Ssch\T3Messenger\Tests\Functional\Mime;

use Ssch\T3Messenger\Tests\Functional\Helper\MailerAssertionsTrait;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\BodyRendererInterface;
use TYPO3\CMS\Core\Mail\FluidEmail;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

final class BodyRendererTest extends FunctionalTestCase
{
    use MailerAssertionsTrait;

    private BodyRendererInterface $subject;

    private MailerInterface $mailer;

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
        $this->subject = $this->get(BodyRendererInterface::class);
        $this->mailer = $this->get(MailerInterface::class);
    }

    public function testThatFluidEmailContentIsRendered(): void
    {
        $fluidEmail = GeneralUtility::makeInstance(FluidEmail::class);
        $fluidEmail
            ->subject('Test')
            ->to('info@test.de');

        $this->subject->render($fluidEmail);
        $this->mailer->send($fluidEmail);
        $this->assertQueuedEmailCount(1, 'null://');
        $this->assertEmailHtmlBodyContains($fluidEmail, 'This email was sent by');
    }
}
