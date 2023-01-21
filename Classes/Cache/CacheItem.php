<?php

declare(strict_types=1);

/*
 * This file is part of the "t3_messenger" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

namespace Ssch\T3Messenger\Cache;

use DateInterval;
use DateTime;
use DateTimeInterface;
use Psr\Cache\CacheItemInterface;
use TypeError;

final class CacheItem implements CacheItemInterface
{
    private string $key;

    /**
     * @var mixed
     */
    private $value;

    private bool $isHit;

    private ?float $expiry = null;

    /**
     * @param mixed $data
     */
    public function __construct(string $key, $data, bool $isHit)
    {
        $this->key = $key;
        $this->value = $data;
        $this->isHit = $isHit;
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function get()
    {
        return $this->value;
    }

    public function isHit(): bool
    {
        return $this->isHit;
    }

    public function set($value)
    {
        $this->value = $value;

        return $this;
    }

    public function expiresAt($expiration): CacheItemInterface
    {
        if ($expiration === null) {
            $this->expiry = null;
        } elseif ($expiration instanceof DateTimeInterface) {
            $this->expiry = (float) $expiration->format('U.u');
        } else {
            throw new TypeError(sprintf(
                'Expected $expiration to be an instance of DateTimeInterface or null, got %s',
                is_object($expiration) ? get_class($expiration) : gettype($expiration)
            ));
        }

        return $this;
    }

    public function expiresAfter($time): CacheItemInterface
    {
        if ($time === null) {
            $this->expiry = null;
        } elseif ($time instanceof DateInterval) {
            $this->expiry = microtime(true) + DateTime::createFromFormat('U', '0')->add($time)->format('U.u');
        } elseif (is_int($time)) {
            $this->expiry = $time + microtime(true);
        } else {
            throw new TypeError(sprintf(
                'Expected $time to be either an integer, an instance of DateInterval or null, got %s',
                is_object($time) ? get_class($time) : gettype($time)
            ));
        }

        return $this;
    }

    /**
     * @internal
     */
    public function getExpiry(): ?float
    {
        return $this->expiry;
    }
}
