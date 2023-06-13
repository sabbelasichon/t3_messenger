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

final class FailedMessage
{
    /**
     * @var class-string
     */
    private string $message;

    private string $errorMessage;

    private ?\DateTimeInterface $redelivered;

    /**
     * @param class-string $message
     */
    private function __construct(string $message, string $errorMessage, DateTimeInterface $redelivered)
    {
        $this->message = $message;
        $this->errorMessage = $errorMessage;
        $this->redelivered = $redelivered;
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

    public static function createFromEnvelope(Envelope $failedMessage): self
    {
        $errorDetailsStamp = $failedMessage->last(ErrorDetailsStamp::class);
        $errorMessage = $errorDetailsStamp ? $errorDetailsStamp->getExceptionMessage() : '';

        $redeliveryStamp = $failedMessage->last(RedeliveryStamp::class);
        $redelivered = $redeliveryStamp ? $redeliveryStamp->getRedeliveredAt() : null;

        if ($redelivered === null) {
            throw new \UnexpectedValueException('No redelivery stamp given');
        }

        return new self(get_class($failedMessage->getMessage()), $errorMessage, $redelivered);
    }
}
