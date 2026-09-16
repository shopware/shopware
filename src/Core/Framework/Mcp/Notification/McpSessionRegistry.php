<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\Notification;

use Psr\SimpleCache\CacheInterface;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Lock\LockFactory;

/**
 * @experimental stableVersion:v6.8.0
 *
 * @internal
 *
 * Tracks the set of active MCP session ids for one endpoint scope as a single cached list. Because
 * register()/remove() are an unlocked read-modify-write on that list, concurrent requests could
 * lose or resurrect ids; a scope-specific lock serializes the mutations so they stay consistent.
 */
#[Package('framework')]
class McpSessionRegistry
{
    private const CACHE_KEY = 'shopware.mcp.active_session_ids';

    /**
     * @param string $cacheKey the cache key holding this scope's active session ids; distinct keys
     *                         keep the Admin and Store API populations isolated even though both
     *                         wrap the same cache pool
     */
    public function __construct(
        private readonly CacheInterface $cache,
        private readonly string $cacheKey = self::CACHE_KEY,
        private readonly ?LockFactory $lockFactory = null,
    ) {
    }

    public function register(string $sessionId): void
    {
        $this->mutate(static function (array $sessionIds) use ($sessionId): array {
            if (\in_array($sessionId, $sessionIds, true)) {
                return $sessionIds;
            }

            $sessionIds[] = $sessionId;

            return $sessionIds;
        });
    }

    public function remove(string $sessionId): void
    {
        $this->mutate(static fn (array $sessionIds): array => array_values(array_filter(
            $sessionIds,
            static fn (string $activeSessionId): bool => $activeSessionId !== $sessionId,
        )));
    }

    /**
     * @return list<string>
     */
    public function all(): array
    {
        $sessionIds = $this->cache->get($this->cacheKey, []);

        if (!\is_array($sessionIds)) {
            return [];
        }

        return array_values(array_filter(
            $sessionIds,
            static fn (mixed $sessionId): bool => \is_string($sessionId) && $sessionId !== '',
        ));
    }

    /**
     * Serializes the read-modify-write against concurrent mutations of the same scope's list.
     *
     * @param callable(list<string>): list<string> $mutation
     */
    private function mutate(callable $mutation): void
    {
        $lock = $this->lockFactory?->createLock('mcp.session_registry.' . $this->cacheKey);
        $lock?->acquire(true);

        try {
            $this->cache->set($this->cacheKey, $mutation($this->all()));
        } finally {
            $lock?->release();
        }
    }
}
