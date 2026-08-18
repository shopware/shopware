<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\Context;

use Psr\Cache\CacheItemPoolInterface;
use Shopware\Core\Framework\Log\Package;

/**
 * Holds the context token a handoff token refers to, keyed by the `jti` of that handoff token.
 *
 * Entries are single use, {@see self::consume()} removes the entry it returns.
 *
 * @internal
 */
#[Package('framework')]
class ContextHandoffTokenStore
{
    private const CACHE_KEY_PREFIX = 'context-handoff-';

    public function __construct(private readonly CacheItemPoolInterface $cache)
    {
    }

    public function store(string $jti, string $contextToken, int $lifetime): void
    {
        $item = $this->cache->getItem(self::CACHE_KEY_PREFIX . $jti);
        $item->set($contextToken);
        $item->expiresAfter($lifetime);

        $this->cache->save($item);
    }

    public function consume(string $jti): ?string
    {
        $key = self::CACHE_KEY_PREFIX . $jti;

        $item = $this->cache->getItem($key);
        if (!$item->isHit()) {
            return null;
        }

        $this->cache->deleteItem($key);

        $contextToken = $item->get();

        return \is_string($contextToken) && $contextToken !== '' ? $contextToken : null;
    }
}
