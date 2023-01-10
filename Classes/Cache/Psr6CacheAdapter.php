<?php

declare(strict_types=1);

/*
 * This file is part of the "t3_tactician" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

namespace Ssch\T3Messenger\Cache;

use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;

final class Psr6CacheAdapter implements CacheItemPoolInterface
{
    private FrontendInterface $cache;

    public function __construct(FrontendInterface $cache)
    {
        $this->cache = $cache;
    }

    public function getItem(string $key): CacheItemInterface
    {
        $data = $this->cache->get($this->hash($key));

        return new CacheItem($key, $data, true);
    }

    public function getItems(array $keys = []): iterable
    {
        $cacheItems = [];
        foreach ($keys as $key) {
            $cacheItems[] = $this->getItem($key);
        }

        return $cacheItems;
    }

    public function hasItem(string $key): bool
    {
        return $this->cache->has($this->hash($key));
    }

    public function clear(): bool
    {
        $this->cache->flush();
    }

    public function deleteItem(string $key): bool
    {
        return $this->cache->remove($this->hash($key));
    }

    public function deleteItems(array $keys): bool
    {
        foreach ($keys as $key) {
            if (! $this->deleteItem($this->hash($key))) {
                return false;
            }
        }

        return true;
    }

    public function save(CacheItemInterface $item): bool
    {
        try {
            $this->cache->set($this->hash($item->getKey()), $item->get());

            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function saveDeferred(CacheItemInterface $item): bool
    {
        throw new \BadMethodCallException('Not implemented yet');
    }

    public function commit(): bool
    {
        throw new \BadMethodCallException('Not implemented yet');
    }

    private function hash(string $key): string
    {
        return sha1($key);
    }
}
