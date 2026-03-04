<?php declare(strict_types=1);

namespace Shopware\Core\Framework\SystemCheck;

use Psr\Cache\CacheItemPoolInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\SystemCheck\Check\Category;
use Shopware\Core\Framework\SystemCheck\Check\Result;
use Shopware\Core\Framework\SystemCheck\Check\Status;
use Shopware\Core\Framework\SystemCheck\Check\SystemCheckExecutionContext;
use Shopware\Core\Framework\SystemCheck\Event\SystemHeartbeatEvent;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('framework')]
class SystemHeartbeat extends BaseCheck
{
    private const CACHE_KEY = 'system_heartbeat.last_run';

    private const MIN_TIME_BETWEEN_UPDATES = 'PT12H'; // ISO 8601 duration format for 12 hours

    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly CacheItemPoolInterface $cacheItemPool,
    ) {
    }

    public function run(): Result
    {
        $lastBeatCacheItem = $this->cacheItemPool->getItem(self::CACHE_KEY);
        $lastBeatAt = $lastBeatCacheItem->isHit() ? $lastBeatCacheItem->get() : null;
        if ($this->isTooRecent($lastBeatAt)) {
            return new Result(name: $this->name(), status: Status::SKIPPED, message: 'System Heartbeat skipped due to recent execution.', healthy: true);
        }

        $this->eventDispatcher->dispatch(new SystemHeartbeatEvent());
        $lastBeatCacheItem->set(new \DateTimeImmutable());
        $this->cacheItemPool->save($lastBeatCacheItem);

        return new Result(
            name: $this->name(),
            status: Status::OK,
            message: 'System Heartbeat indicated successfully.',
            healthy: true
        );
    }

    public function category(): Category
    {
        return Category::SYSTEM;
    }

    public function name(): string
    {
        return 'System Heartbeat';
    }

    protected function allowedSystemCheckExecutionContexts(): array
    {
        return SystemCheckExecutionContext::cases();
    }

    private function isTooRecent(?\DateTimeInterface $lastBeatAt): bool
    {
        if ($lastBeatAt === null) {
            // If we don't have a last updated time, we assume it's not too recent.
            return false;
        }

        $threshold = (new \DateTimeImmutable())->sub(new \DateInterval(self::MIN_TIME_BETWEEN_UPDATES));

        return $lastBeatAt > $threshold;
    }
}
