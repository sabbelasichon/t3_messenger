<?php

declare(strict_types=1);

/*
 * This file is part of the "t3_messenger" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

namespace Ssch\T3Messenger\Dashboard\Widgets\Provider;

use Ssch\T3Messenger\Repository\FailedMessageRepository;
use TYPO3\CMS\Dashboard\Widgets\ListDataProviderInterface;

final class FailedMessagesDataProvider implements ListDataProviderInterface
{
    private FailedMessageRepository $failedMessageRepository;

    public function __construct(FailedMessageRepository $failedMessageRepository)
    {
        $this->failedMessageRepository = $failedMessageRepository;
    }

    public function getItems(): array
    {
        return $this->failedMessageRepository->list();
    }
}
