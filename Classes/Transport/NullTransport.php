<?php
declare(strict_types=1);


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
