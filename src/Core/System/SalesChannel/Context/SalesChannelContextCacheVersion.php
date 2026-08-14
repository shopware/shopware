<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\Context;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * @internal
 */
#[Package('framework')]
class SalesChannelContextCacheVersion
{
    private const CACHE_KEY = 'sales-channel-context-version';

    public function __construct(private readonly CacheInterface $cache)
    {
    }

    public function get(): string
    {
        return $this->cache->get(self::CACHE_KEY, static fn (): string => Uuid::randomHex());
    }

    public function invalidate(): void
    {
        $this->cache->delete(self::CACHE_KEY);
    }
}
