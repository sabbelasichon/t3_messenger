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
use Ssch\T3Messenger\Tests\Functional\Fixtures\Extensions\t3_messenger_test\Classes\Command\MyFailingCommand;
use Ssch\T3Messenger\Tests\Functional\Fixtures\Extensions\t3_messenger_test\Classes\Command\MyOtherCommand;
use Ssch\T3Messenger\Tests\Functional\Fixtures\Extensions\t3_messenger_test\Classes\Service\MyService;
use Symfony\Component\Messenger\EventListener\StopWorkerOnFailureLimitListener;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Messenger\Transport\TransportInterface;
use Symfony\Component\Messenger\Worker;
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

    public function testThatInvalidCommandThrowsAValidationException(): void
    {
        $this->expectException(ValidationFailedException::class);

        $this->get(MyService::class)->dispatch(new MyCommand('notvalidemail'));
    }

    public function testThatCommandIsHandledSynchronously(): void
    {
        $envelope = $this->get(MyService::class)->dispatch(new MyOtherCommand('note'));
        $handledStamps = $envelope->all(HandledStamp::class);
        self::assertCount(1, $handledStamps);
    }

    public function testThatFailingCommandIsTransferredToFailureTransport(): void
    {
        $this->get(MyService::class)->dispatch(new MyFailingCommand('note'));

        $receivers = [
            'async' => $this->get('messenger.transport.async'),
        ];

        /** @var \Symfony\Component\EventDispatcher\EventDispatcher $eventDispatcher */
        $eventDispatcher = $this->get('event_dispatcher');
        $eventDispatcher->addSubscriber(new StopWorkerOnFailureLimitListener(1));

        $worker = new Worker($receivers, $this->get('command.bus'), $eventDispatcher);
        $worker->run();

        /** @var TransportInterface $transport */
        $transport = $this->get('messenger.transport.failed');
        self::assertCount(1, $transport->get());
    }
}
