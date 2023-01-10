<?php

declare(strict_types=1);

/*
 * This file is part of the "t3_messenger" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

namespace Ssch\T3Messenger\Tests\Functional;

use Ssch\T3Messenger\Tests\Functional\Fixtures\Extensions\t3_messenger_test\Classes\Service\MyService;
use Symfony\Component\Messenger\Transport\TransportInterface;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

final class MessengerTest extends FunctionalTestCase
{
    protected $testExtensionsToLoad = [
        'typo3conf/ext/t3_messenger',
        'typo3conf/ext/t3_messenger/Tests/Functional/Fixtures/Extensions/t3_messenger_test',
    ];

    public function testThatCommandIsRoutedToAsyncTransportSuccessfully(): void
    {
        $this->get(MyService::class)->dispatch();

        /** @var TransportInterface $transport */
        $transport = $this->get('messenger.transport.async');
        self::assertCount(1, $transport->get());
    }
}
