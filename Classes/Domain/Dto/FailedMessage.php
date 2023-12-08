<?php

declare(strict_types=1);

/*
 * This file is part of the "t3_messenger" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

namespace Ssch\T3Messenger\Domain\Dto;

use DateTimeInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\ErrorDetailsStamp;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;

final class FailedMessage
{
    /**
     * @var class-string
     */
    private string $message;

    private string $errorMessage;

    private DateTimeInterface $redelivered;

    private int $retryCount;

    /**
     * @var mixed
     */
    private $messageId;

    private string $transportName;

    private string $shortMessageClass;

    /**
     * @param class-string $message
     * @param mixed $messageId
     */
    private function __construct(
        string $message,
        string $shortMessageClass,
        string $errorMessage,
        DateTimeInterface $redelivered,
        int $retryCount,
        $messageId,
        string $transportName
    ) {
        $this->message = $message;
        $this->errorMessage = $errorMessage;
        $this->redelivered = $redelivered;
        $this->retryCount = $retryCount;
        $this->messageId = $messageId;
        $this->transportName = $transportName;
        $this->shortMessageClass = $shortMessageClass;
    }

    public function getShortMessageClass(): string
    {
        return $this->shortMessageClass;
    }

    /**
     * @return mixed
     */
    public function getMessageId()
    {
        return $this->messageId;
    }

    /**
     * @return class-string
     */
    public function getMessage(): string
    {
        return $this->message;
    }

    public function getRedelivered(): DateTimeInterface
    {
        return $this->redelivered;
    }

    public function getErrorMessage(): string
    {
        return $this->errorMessage;
    }

    public function getTransportName(): string
    {
        return $this->transportName;
    }

    public function getRetryCount(): int
    {
        return $this->retryCount;
    }

    public static function createFromEnvelope(Envelope $failedMessage, string $transportName): self
    {
        $errorDetailsStamp = $failedMessage->last(ErrorDetailsStamp::class);

        if (! $errorDetailsStamp instanceof ErrorDetailsStamp) {
            throw new \UnexpectedValueException('No error details stamp given');
        }

        $errorMessage = $errorDetailsStamp->getExceptionMessage();

        $redeliveryStamp = $failedMessage->last(RedeliveryStamp::class);

        if (! $redeliveryStamp instanceof RedeliveryStamp) {
            throw new \UnexpectedValueException('No redelivery stamp given');
        }

        $transportMessageIdStamp = $failedMessage->last(TransportMessageIdStamp::class);
        if (! $transportMessageIdStamp instanceof TransportMessageIdStamp) {
            throw new \UnexpectedValueException('No transport message id stamp given');
        }

        return new self(
            get_class($failedMessage->getMessage()),
            (new \ReflectionClass($failedMessage->getMessage()))->getShortName(),
            $errorMessage,
            $redeliveryStamp->getRedeliveredAt(),
            $redeliveryStamp->getRetryCount(),
            $transportMessageIdStamp->getId(),
            $transportName
        );
    }
}
