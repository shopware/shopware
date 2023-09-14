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
        private readonly int $delay,
        private readonly array $adapters,
        private readonly AbstractInvalidatorStorage $cache,
        private readonly EventDispatcherInterface $dispatcher,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @param list<string> $tags
     */
    public function invalidate(array $tags, bool $force = false): void
    {
        /** @var list<string> $tags */
        $tags = array_filter(array_unique($tags));

        if (empty($tags)) {
            return;
        }

        if ($this->delay > 0 && !$force) {
            $this->cache->store($tags);

            return;
        }

        $this->purge($tags);
    }

    /**
     * @deprecated tag:v6.6.0 - The parameter $time is obsolete and will be removed in v6.6.0.0
     */
    public function invalidateExpired(?\DateTime $time = null): void
    {
        if ($time) {
            Feature::triggerDeprecationOrThrow('v6.6.0.0', 'The parameter $time in \Shopware\Core\Framework\Adapter\Cache\CacheInvalidator::invalidateExpired is obsolete and will be removed in v6.6.0.0');
        }

        $tags = $this->cache->loadAndDelete();

        if (empty($tags)) {
            return;
        }

        $this->logger->debug(sprintf('Purged %d tags', \count($tags)));

        $this->purge($tags);
    }

    /**
     * @param list<string> $keys
     */
    private function purge(array $keys): void
    {
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
