<?php

declare(strict_types=1);

/*
 * This file is part of the "t3_messenger" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

namespace Ssch\T3Messenger\Tests\Functional\Repository;

use Ssch\T3Messenger\Repository\FailedMessageRepository;
use Ssch\T3Messenger\Tests\Functional\Fixtures\Extensions\t3_messenger_test\Classes\Command\MyFailingCommand;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Messenger\EventListener\StopWorkerOnFailureLimitListener;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Worker;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

final class FailedMessageRepositoryTest extends FunctionalTestCase
{
    private FailedMessageRepository $subject;

    private MessageBusInterface $messageBus;

    protected function setUp(): void
    {
        $this->testExtensionsToLoad = [
            'typo3conf/ext/typo3_psr_cache_adapter',
            'typo3conf/ext/t3_messenger',
            'typo3conf/ext/t3_messenger/Tests/Functional/Fixtures/Extensions/t3_messenger_test',
        ];
        parent::setUp();
        $this->subject = $this->get(FailedMessageRepository::class);
        $this->messageBus = $this->get(MessageBusInterface::class);
    }

    public function test(): void
    {
        // Arrange
        $this->messageBus->dispatch(new MyFailingCommand('Add to failed queue'));
        $this->runWorker();

        // Act
        $failedMessages = $this->subject->list();

        // Assert
        self::assertSame(MyFailingCommand::class, $failedMessages[0]->getMessage());
        self::assertSame('Failing by intention', $failedMessages[0]->getErrorMessage());
    }

    private function runWorker(): void
    {
        $receivers = [
            'async' => $this->get('messenger.transport.async'),
        ];

        /** @var EventDispatcher $eventDispatcher */
        $eventDispatcher = $this->get('event_dispatcher');
        $eventDispatcher->addSubscriber(new StopWorkerOnFailureLimitListener(1));

        $worker = new Worker($receivers, $this->get('command.bus'), $eventDispatcher);
        $worker->run();
    }
}
