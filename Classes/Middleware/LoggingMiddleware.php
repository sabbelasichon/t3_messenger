<?php

declare(strict_types=1);

/*
 * This file is part of the "t3_messenger" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

namespace Ssch\T3Messenger\Middleware;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;

final class LoggingMiddleware implements MiddlewareInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function __construct()
    {
        $this->logger = new NullLogger();
    }

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        $message = get_class($envelope->getMessage());

        $this->logger->info(sprintf('Executing message "%s"', $message));

        try {
            $nextEnvelope = $stack->next()
                ->handle($envelope, $stack);

            $this->logger->info(sprintf('Message "%s" successfully executed', $message));

            return $nextEnvelope;
        } catch (\Exception $exception) {
            $this->logger->error(sprintf('Failed executing message "%s"', $message));

            throw $exception;
        }
    }
}
