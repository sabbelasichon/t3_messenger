<?php

declare(strict_types=1);

/*
 * This file is part of the "t3_messenger" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

namespace Ssch\T3Messenger\Tests\Functional\Fixtures\Extensions\t3_messenger_test\Classes\EventListener;

use Ssch\T3Messenger\Event\PreRejectEvent;

final class PreRejectEventListener
{
    /**
     * @var PreRejectEvent[]
     */
    private array $events = [];

    public function __invoke(PreRejectEvent $event): void
    {
        $this->events[] = $event;
    }

    public function getEvents(): array
    {
        return $this->events;
    }
}
