<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\Context;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Cache\Adapter\TagAwareAdapterInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Wraps tagged cache misses that may be invalidated while their value is being computed.
 *
 * A unique, tagged marker is stored before the callback starts. If invalidation removes the marker before the
 * callback completes, the fresh value is returned to the current caller but Symfony is instructed not to cache it.
 * The marker stays present until the underlying cache has finished saving the primary item, then it is removed.
 *
 * Use this only for expensive computations where a tagged invalidation can realistically overlap value creation.
 * Ordinary short-lived cache reads should use the cache adapter directly and avoid the marker overhead.
 *
 * @internal
 */
#[Package('framework')]
class InvalidationRaceAwareCache
{
    public function __construct(
        private readonly CacheInterface&TagAwareAdapterInterface $cache,
    ) {
    }

    /**
     * @param list<string> $tags
     * @param callable(): mixed $callback
     */
    public function get(string $key, array $tags, callable $callback): mixed
    {
        $markerKey = null;

        try {
            return $this->cache->get($key, function (ItemInterface $item, bool &$save) use ($key, $tags, $callback, &$markerKey): mixed {
                $item->tag($tags);

                $markerKey = $key . '-marker-' . Uuid::randomHex();
                $this->createMarker($markerKey, $tags);

                $value = $callback();

                if (!$this->cache->hasItem($markerKey)) {
                    // A tagged invalidation overlapped cache value creation. Return the fresh value, but do not cache the potentially stale result.
                    $save = false;
                }

                return $value;
            });
        } finally {
            if ($markerKey !== null) {
                $this->cache->deleteItem($markerKey);
            }
        }
    }

    /**
     * @param list<string> $tags
     */
    private function createMarker(string $markerKey, array $tags): void
    {
        $marker = $this->cache->getItem($markerKey);
        $marker->set(true);
        $marker->tag($tags);
        $this->cache->save($marker);
    }
}
