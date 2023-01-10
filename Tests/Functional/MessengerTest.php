<?php

declare(strict_types=1);

/*
 * This file is part of the "t3_messenger" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

namespace Ssch\T3Messenger\Tests\Functional;

use Ssch\T3Messenger\Exception\ValidationFailedException;
use Ssch\T3Messenger\Tests\Functional\Fixtures\Extensions\t3_messenger_test\Classes\Command\MyCommand;
use Ssch\T3Messenger\Tests\Functional\Fixtures\Extensions\t3_messenger_test\Classes\Service\MyService;
use Symfony\Component\Messenger\Transport\TransportInterface;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

final class MessengerTest extends FunctionalTestCase
{
    protected $testExtensionsToLoad = [
        'typo3conf/ext/t3_messenger',
        'typo3conf/ext/t3_messenger/Tests/Functional/Fixtures/Extensions/t3_messenger_test',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['LANG'] = $this->get(LanguageService::class);
    }

    public function testThatCommandIsRoutedToAsyncTransportSuccessfully(): void
    {
        $this->get(MyService::class)->dispatch(new MyCommand('max.mustermann@domain.com'));

        /** @var TransportInterface $transport */
        $transport = $this->get('messenger.transport.async');
        self::assertCount(1, $transport->get());
    }

    public function testThatInvalidCommandThrowsAnValidationException(): void
    {
        $this->expectException(ValidationFailedException::class);

        $this->get(MyService::class)->dispatch(new MyCommand('notvalidemail'));
    }
}
