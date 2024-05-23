<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Cache;

use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Adapter\Cache\InvalidatorStorage\AbstractInvalidatorStorage;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Cache\Adapter\TagAwareAdapterInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @final
 */
#[Package('core')]
class CacheInvalidator
{
    /**
     * @internal
     *
     * @param CacheItemPoolInterface[] $adapters
     */
    public function __construct(
        // @deprecated tag:v6.7.0 - #cache_rework_rule_reason#
        private readonly int $delay,
        private readonly array $adapters,
        private readonly AbstractInvalidatorStorage $cache,
        private readonly EventDispatcherInterface $dispatcher,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @param array<string> $tags
     */
    public function invalidate(array $tags, bool $force = false): void
    {
        $tags = array_filter(array_unique($tags));

        if (empty($tags)) {
            return;
        }

        $delay = $this->delay > 0 && !$force;
        if (Feature::isActive('cache_rework')) {
            $delay = !$force;
        }

        if ($delay) {
            $this->cache->store($tags);

            return;
        }

        $this->purge($tags);
    }

    public function invalidateExpired(): array
    {
        $tags = $this->cache->loadAndDelete();

        if (empty($tags)) {
            return $tags;
        }

        $this->logger->info(\sprintf('Purged %d tags', \count($tags)));

        $this->purge($tags);

        return $tags;
    }

    /**
     * @param array<string> $keys
     */
    private function purge(array $keys): void
    {
        foreach ($this->adapters as $adapter) {
            $adapter->deleteItems($keys);

            if ($adapter instanceof TagAwareAdapterInterface) {
                $adapter->invalidateTags($keys);
            }
        }

        $this->dispatcher->dispatch(new InvalidateCacheEvent($keys));
    }
}
