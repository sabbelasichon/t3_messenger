<?php

declare(strict_types=1);

/*
 * This file is part of the "t3_messenger" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

namespace Ssch\T3Messenger\Repository;

use Ssch\T3Messenger\Dashboard\Widgets\Dto\MessageSpecification;
use Ssch\T3Messenger\Domain\Dto\FailedMessage;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Messenger\Envelope;
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

            $failedMessages = $this->inReverseOrder($failureTransport->all());

            foreach ($failedMessages as $failedMessage) {
                $allFailedMessages[] = FailedMessage::createFromEnvelope($failedMessage, $serviceId);
            }
        }

        return $allFailedMessages;
    }

    public function removeMessage(MessageSpecification $messageSpecification): void
    {
        $failureTransport = $this->failureTransports->get($messageSpecification->getTransport());

        if (! $failureTransport instanceof ListableReceiverInterface) {
            throw new RuntimeException(sprintf(
                'The "%s" receiver does not support removing specific messages.',
                $messageSpecification->getTransport()
            ));
        }

        $envelope = $failureTransport->find($messageSpecification->getId());

        if ($envelope === null) {
            throw new RuntimeException(sprintf(
                'The message with id "%s" was not found.',
                $messageSpecification->getId()
            ));
        }

        $failureTransport->reject($envelope);
    }

    /**
     * @param Envelope[] $failedMessages
     *
     * @return Envelope[];
     */
    private function inReverseOrder(iterable $failedMessages): array
    {
        if (! is_array($failedMessages)) {
            $failedMessages = iterator_to_array($failedMessages);
        }

        return array_reverse($failedMessages);
    }
}
