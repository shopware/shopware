<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\Context;

use Shopware\Core\Framework\Adapter\Cache\CacheValueCompressor;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Hasher;
use Shopware\Core\System\SalesChannel\BaseSalesChannelContext;

/**
 * @internal
 */
#[Package('framework')]
class CachedBaseSalesChannelContextFactory extends AbstractBaseSalesChannelContextFactory
{
    public function __construct(
        private readonly AbstractBaseSalesChannelContextFactory $decorated,
        private readonly InvalidationRaceAwareCache $cache,
    ) {
    }

    public function create(string $salesChannelId, array $options = []): BaseSalesChannelContext
    {
        if (isset($options[SalesChannelContextService::ORIGINAL_CONTEXT])) {
            return $this->decorated->create($salesChannelId, $options);
        }
        if (isset($options[SalesChannelContextService::PERMISSIONS])) {
            return $this->decorated->create($salesChannelId, $options);
        }

        $name = self::buildName($salesChannelId);

        ksort($options);

        $keys = \array_intersect_key($options, [
            SalesChannelContextService::CURRENCY_ID => true,
            SalesChannelContextService::LANGUAGE_ID => true,
            SalesChannelContextService::DOMAIN_ID => true,
            SalesChannelContextService::PAYMENT_METHOD_ID => true,
            SalesChannelContextService::SHIPPING_METHOD_ID => true,
            SalesChannelContextService::VERSION_ID => true,
            SalesChannelContextService::COUNTRY_ID => true,
            SalesChannelContextService::COUNTRY_STATE_ID => true,
        ]);

        $key = implode('-', [$name, Hasher::hash($keys)]);
        $tags = [$name, CachedSalesChannelContextFactory::ALL_TAG];
        $fresh = null;

        $value = $this->cache->get($key, $tags, function () use ($salesChannelId, $options, &$fresh): string {
            $fresh = $this->decorated->create($salesChannelId, $options);

            return CacheValueCompressor::compress($fresh);
        });

        // The context was built in this call, return it directly instead of uncompressing the cache payload that was just compressed from it.
        if ($fresh instanceof BaseSalesChannelContext) {
            return $fresh;
        }

        return CacheValueCompressor::uncompress($value);
    }

    public static function buildName(string $salesChannelId): string
    {
        return 'base-context-factory-' . $salesChannelId;
    }
}
