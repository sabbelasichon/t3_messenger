<?php

declare(strict_types=1);

/*
 * This file is part of the "t3_messenger" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

namespace Ssch\T3Messenger\Repository;

use Psr\Log\LoggerInterface;
use Ssch\T3Messenger\Dashboard\Widgets\Dto\MessageSpecification;
use Ssch\T3Messenger\Domain\Dto\FailedMessage;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\EventListener\StopWorkerOnMessageLimitListener;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Transport\Receiver\ListableReceiverInterface;
use Symfony\Component\Messenger\Transport\Receiver\SingleMessageReceiver;
use Symfony\Component\Messenger\Worker;
use Symfony\Contracts\Service\ServiceProviderInterface;
use TYPO3\CMS\Core\SingletonInterface;

final class FailedMessageRepository implements SingletonInterface
{
    private ServiceProviderInterface $failureTransports;

    private MessageBusInterface $messageBus;

    private EventDispatcher $eventDispatcher;

    private LoggerInterface $logger;

    public function __construct(ServiceProviderInterface $failureTransports, EventDispatcher $eventDispatcher, MessageBusInterface $messageBus, LoggerInterface $logger)
    {
        $this->failureTransports = $failureTransports;
        $this->messageBus = $messageBus;
        $this->eventDispatcher = $eventDispatcher;
        $this->logger = $logger;
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
        $failureTransport = $this->getReceiver($messageSpecification->getTransport());

        $envelope = $failureTransport->find($messageSpecification->getId());

        if ($envelope === null) {
            throw new RuntimeException(sprintf(
                'The message with id "%s" was not found.',
                $messageSpecification->getId()
            ));
        }

        $failureTransport->reject($envelope);
    }

    public function retryMessage(MessageSpecification $messageSpecification): void
    {
        $failureTransport = $this->getReceiver($messageSpecification->getTransport());

        $envelope = $failureTransport->find($messageSpecification->getId());

        if ($envelope === null) {
            throw new RuntimeException(sprintf(
                'The message with id "%s" was not found.',
                $messageSpecification->getId()
            ));
        }

        $singleReceiver = new SingleMessageReceiver($failureTransport, $envelope);

        $subscriber = new StopWorkerOnMessageLimitListener(1);
        $this->eventDispatcher->addSubscriber($subscriber);

        $worker = new Worker(
            [
                $messageSpecification->getTransport() => $singleReceiver,
            ],
            $this->messageBus,
            $this->eventDispatcher,
            $this->logger
        );

        $worker->run();
        $this->eventDispatcher->removeSubscriber($subscriber);
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

    private function getReceiver(string $transport): ListableReceiverInterface
    {
        $failureTransport = $this->failureTransports->get($transport);

        if (! $failureTransport instanceof ListableReceiverInterface) {
            throw new RuntimeException(sprintf(
                'The "%s" receiver does not support removing specific messages.',
                $transport
            ));
        }

        return $failureTransport;
    }
}
