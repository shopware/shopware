<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\Notification;

use Psr\SimpleCache\CacheInterface;
use Shopware\Core\Framework\Log\Package;

/**
 * @experimental stableVersion:v6.8.0 feature:MCP_SERVER
 *
 * @internal
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
    ) {
    }

    public function register(string $sessionId): void
    {
        $sessionIds = $this->all();

        if (\in_array($sessionId, $sessionIds, true)) {
            return;
        }

        $sessionIds[] = $sessionId;
        $this->cache->set($this->cacheKey, $sessionIds);
    }

    public function remove(string $sessionId): void
    {
        $sessionIds = array_values(array_filter(
            $this->all(),
            static fn (string $activeSessionId): bool => $activeSessionId !== $sessionId,
        ));

        $this->cache->set($this->cacheKey, $sessionIds);
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
}
