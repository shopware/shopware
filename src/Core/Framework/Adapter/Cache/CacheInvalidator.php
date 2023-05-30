<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Cache;

use Psr\Cache\CacheItemPoolInterface;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Cache\Adapter\TagAwareAdapterInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @final
 */
#[Package('core')]
class CacheInvalidator
{
    private const CACHE_KEY = 'invalidation';

    /**
     * @internal
     *
     * @param CacheItemPoolInterface[] $adapters
     */
    public function __construct(
        private readonly int $delay,
        private readonly int $count,
        private readonly array $adapters,
        private readonly TagAwareAdapterInterface $cache,
        private readonly EventDispatcherInterface $dispatcher
    ) {
    }

    /**
     * @param list<string> $tags
     */
    public function invalidate(array $tags, bool $force = false): void
    {
        $tags = array_filter(array_unique($tags));

        if (empty($tags)) {
            return;
        }

        if ($this->delay > 0 && !$force) {
            $this->log($tags);

            return;
        }

        $this->purge($tags);
    }

    public function invalidateExpired(?\DateTime $time): void
    {
        $item = $this->cache->getItem(self::CACHE_KEY);

        $values = CacheCompressor::uncompress($item) ?? [];

        $invalidate = [];
        foreach ($values as $key => $timestamp) {
            $timestamp = new \DateTime($timestamp);

            if ($time !== null && $timestamp > $time) {
                continue;
            }

            $invalidate[] = $key;
            unset($values[$key]);

            if (\count($invalidate) > $this->count) {
                break;
            }
        }

        $item = CacheCompressor::compress($item, $values);

        $this->cache->save($item);
        $this->purge($invalidate);
    }

    /**
     * @param list<string> $logs
     */
    private function log(array $logs): void
    {
        $item = $this->cache->getItem(self::CACHE_KEY);

        $values = CacheCompressor::uncompress($item) ?? [];

        $time = (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT);
        foreach ($logs as $log) {
            $values[$log] = $time;
        }

        $item = CacheCompressor::compress($item, $values);

        $this->cache->save($item);
    }

    /**
     * @param list<string> $keys
     */
    private function purge(array $keys): void
    {
        $keys = array_unique(array_filter($keys));

        if (empty($keys)) {
            return;
        }

        foreach ($this->adapters as $adapter) {
            if ($adapter instanceof TagAwareAdapterInterface) {
                $adapter->invalidateTags($keys);
            }
        }

        foreach ($this->adapters as $adapter) {
            $adapter->deleteItems($keys);
        }

        $this->dispatcher->dispatch(new InvalidateCacheEvent($keys));
    }
}
