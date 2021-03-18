<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Cache;

use Psr\Cache\CacheItemPoolInterface;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Symfony\Component\Cache\Adapter\TagAwareAdapterInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class CacheInvalidationLogger extends ScheduledTaskHandler
{
    private const CACHE_KEY = 'invalidation';

    /**
     * @var CacheItemPoolInterface[]
     */
    private array $adapters;

    private TagAwareAdapterInterface $cache;

    private EventDispatcherInterface $dispatcher;

    private int $delay;

    private int $count;

    public function __construct(
        int $delay,
        int $count,
        array $adapters,
        TagAwareAdapterInterface $cache,
        EventDispatcherInterface $dispatcher,
        EntityRepositoryInterface $scheduledTaskRepository
    ) {
        parent::__construct($scheduledTaskRepository);
        $this->dispatcher = $dispatcher;
        $this->adapters = $adapters;
        $this->scheduledTaskRepository = $scheduledTaskRepository;
        $this->cache = $cache;
        $this->delay = $delay;
        $this->count = $count;
    }

    public static function getHandledMessages(): iterable
    {
        return [InvalidateCacheTask::class];
    }

    public function run(): void
    {
        if (!Feature::isActive('FEATURE_NEXT_10514')) {
            return;
        }

        try {
            if ($this->delay <= 0) {
                $this->invalidate(null);

                return;
            }

            $time = new \DateTime();
            $time->modify(sprintf('-%s second', $this->delay));
            $this->invalidate($time);
        } catch (\Throwable $e) {
        }
    }

    public function log(array $logs): void
    {
        if (!Feature::isActive('FEATURE_NEXT_10514')) {
            return;
        }
        $keys = array_filter(array_unique($logs));

        if (empty($keys)) {
            return;
        }

        if ($this->delay > 0) {
            $this->logToStorage($logs);

            return;
        }

        $this->purge($keys);
    }

    public function invalidate(?\DateTime $time): void
    {
        if (!Feature::isActive('FEATURE_NEXT_10514')) {
            return;
        }

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

    private function logToStorage(array $logs): void
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
