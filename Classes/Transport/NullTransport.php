<?php

declare(strict_types=1);

/*
 * This file is part of the "t3_messenger" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

namespace Ssch\T3Messenger\Transport;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\InvalidArgumentException;
use Symfony\Component\Messenger\Transport\TransportInterface;

final class NullTransport implements TransportInterface
{
    public function get(): iterable
    {
        throw new InvalidArgumentException('You cannot receive messages from the Messenger NullTransport.');
    }

    public function ack(Envelope $envelope): void
    {
        throw new InvalidArgumentException('You cannot call ack() on the Messenger NullTransport.');
    }

    public function reject(Envelope $envelope): void
    {
        throw new InvalidArgumentException('You cannot call reject() on the Messenger NullTransport.');
    }

    public function send(Envelope $envelope): Envelope
    {
        return $envelope;
    }
}
