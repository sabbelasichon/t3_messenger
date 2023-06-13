<?php

declare(strict_types=1);

/*
 * This file is part of the "t3_messenger" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

namespace Ssch\T3Messenger\Repository;

use Ssch\T3Messenger\Domain\Dto\FailedMessage;
use Symfony\Component\Messenger\Transport\Receiver\ListableReceiverInterface;
use Symfony\Contracts\Service\ServiceProviderInterface;
use TYPO3\CMS\Core\SingletonInterface;

final class FailedMessageRepository implements SingletonInterface
{
    private ServiceProviderInterface $failureTransports;

    public function __construct(ServiceProviderInterface $failureTransports)
    {
        $this->failureTransports = $failureTransports;
    }

    /**
     * @return FailedMessage[]
     */
    public function list(): array
    {
        $allFailedMessages = [];
        foreach ($this->failureTransports->getProvidedServices() as $serviceId => $_) {
            $failureTransport = $this->failureTransports->get($serviceId);
            if (! $failureTransport instanceof ListableReceiverInterface) {
                continue;
            }

            $failedMessages = $failureTransport->all();

            foreach ($failedMessages as $failedMessage) {
                $allFailedMessages[] = FailedMessage::createFromEnvelope($failedMessage);
            }
        }

        return $allFailedMessages;
    }
}
