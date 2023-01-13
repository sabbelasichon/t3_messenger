<?php

declare(strict_types=1);

/*
 * This file is part of the "t3_messenger" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

namespace Ssch\T3Messenger\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use TYPO3\CMS\Extbase\Persistence\PersistenceManagerInterface;

/**
 * Clears persistence manager state between messages being handled to avoid outdated data.
 */
final class ExtbaseClearPersistenceStateWorkerSubscriber implements EventSubscriberInterface
{
    private PersistenceManagerInterface $persistenceManager;

    public function __construct(PersistenceManagerInterface $persistenceManager)
    {
        $this->persistenceManager = $persistenceManager;
    }

    public function onWorkerMessageHandled(): void
    {
        $this->persistenceManager->clearState();
    }

    public function onWorkerMessageFailed(): void
    {
        $this->persistenceManager->clearState();
    }

    public static function getSubscribedEvents(): array
    {
        return [
            WorkerMessageHandledEvent::class => 'onWorkerMessageHandled',
            WorkerMessageFailedEvent::class => 'onWorkerMessageFailed',
        ];
    }
}
