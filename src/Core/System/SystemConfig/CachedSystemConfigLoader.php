<?php declare(strict_types=1);

namespace Shopware\Core\System\SystemConfig;

use Shopware\Core\Framework\Adapter\Cache\CacheValueCompressor;
use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

#[Package('framework')]
class CachedSystemConfigLoader extends AbstractSystemConfigLoader
{
    final public const CACHE_TAG = 'system-config';

    /**
     * @internal
     */
    public function __construct(
        private readonly AbstractSystemConfigLoader $decorated,
        private readonly CacheInterface $cache
    ) {
    }

    /**
     * Tag of the cached config of one sales channel.
     * The config of the global scope is inherited by all sales channels, invalidate `CACHE_TAG` for a change there.
     */
    public static function buildSalesChannelTag(string $salesChannelId): string
    {
        return self::CACHE_TAG . '-sales-channel-' . $salesChannelId;
    }

    public function getDecorated(): AbstractSystemConfigLoader
    {
        return $this->decorated;
    }

    public function load(?string $salesChannelId): array
    {
        $key = 'system-config-' . $salesChannelId;

        $fresh = null;

        $value = $this->cache->get($key, function (ItemInterface $item) use ($salesChannelId, &$fresh) {
            $fresh = $this->getDecorated()->load($salesChannelId);

            $tags = [self::CACHE_TAG];
            if ($salesChannelId !== null && $salesChannelId !== '') {
                $tags[] = self::buildSalesChannelTag($salesChannelId);
            }

            $item->tag($tags);

            return CacheValueCompressor::compress($fresh);
        });

        // the config was loaded in this call, return it directly instead of
        // uncompressing the cache payload that was just compressed from it
        if ($fresh !== null) {
            return $fresh;
        }

        return CacheValueCompressor::uncompress($value);
    }
}
