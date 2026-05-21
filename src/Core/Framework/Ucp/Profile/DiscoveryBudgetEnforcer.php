<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Ucp\Profile;

use Psr\Cache\CacheItemPoolInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Ucp\UcpException;
use Shopware\Core\Framework\Util\Hasher;

/**
 * @experimental stableVersion:v6.8.0 feature:UCP_SERVER
 *
 * Bounds outbound platform-profile fetches:
 *   - Global rate limit per minute
 *   - Per-origin backoff on persistent failure
 *
 * Implemented as Symfony Cache-backed counters; works with any cache pool
 * configured for UCP (default: `cache.app`).
 *
 * @internal
 */
#[Package('framework')]
class DiscoveryBudgetEnforcer
{
    private const GLOBAL_BUDGET_KEY = 'ucp_discovery.global_minute';
    private const FAILURE_KEY_PREFIX = 'ucp_discovery.failures.';

    public function __construct(
        private readonly CacheItemPoolInterface $cache,
        private readonly int $globalPerMinute = 120,
        private readonly int $failureBackoffSeconds = 600,
        private readonly int $failureThreshold = 5,
    ) {
    }

    public function assertBudget(string $profileUri): void
    {
        $bucketKey = self::GLOBAL_BUDGET_KEY . '.' . date('YmdHi');
        $item = $this->cache->getItem($bucketKey);
        $count = (int) ($item->get() ?? 0);

        if ($count >= $this->globalPerMinute) {
            throw UcpException::discoveryBudgetExceeded();
        }

        $item->set($count + 1);
        $item->expiresAfter(120);
        $this->cache->save($item);

        $this->assertNotInBackoff($profileUri);
    }

    public function recordFailure(string $profileUri): void
    {
        $origin = $this->originOf($profileUri);
        $key = self::FAILURE_KEY_PREFIX . $origin;
        $item = $this->cache->getItem($key);
        $count = (int) ($item->get() ?? 0) + 1;
        $item->set($count);
        $item->expiresAfter($this->failureBackoffSeconds);
        $this->cache->save($item);
    }

    public function recordSuccess(string $profileUri): void
    {
        $key = self::FAILURE_KEY_PREFIX . $this->originOf($profileUri);
        $this->cache->deleteItem($key);
    }

    private function assertNotInBackoff(string $profileUri): void
    {
        $key = self::FAILURE_KEY_PREFIX . $this->originOf($profileUri);
        $item = $this->cache->getItem($key);
        $count = (int) ($item->get() ?? 0);

        if ($count >= $this->failureThreshold) {
            throw UcpException::profileUnreachable($profileUri, 'origin in backoff period after repeated failures');
        }
    }

    private function originOf(string $profileUri): string
    {
        $parts = parse_url($profileUri);
        if (!\is_array($parts) || !isset($parts['host'])) {
            return Hasher::hash($profileUri);
        }

        return strtolower($parts['host']);
    }
}
