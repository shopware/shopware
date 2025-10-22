<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\Context;

use Shopware\Core\Framework\Adapter\Cache\CacheValueCompressor;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Hasher;
use Shopware\Core\System\SalesChannel\BaseSalesChannelContext;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * @internal
 */
#[Package('framework')]
class CachedBaseSalesChannelContextFactory extends AbstractBaseSalesChannelContextFactory
{
    public function __construct(
        private readonly AbstractBaseSalesChannelContextFactory $decorated,
        private readonly CacheInterface $cache,
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

        $value = $this->cache->get($key, function (ItemInterface $item) use ($name, $salesChannelId, $options) {
            $item->tag([$name, CachedSalesChannelContextFactory::ALL_TAG]);

            return CacheValueCompressor::compress(
                $this->decorated->create($salesChannelId, $options)
            );
        });

        return CacheValueCompressor::uncompress($value);
    }

    public static function buildName(string $salesChannelId): string
    {
        return 'base-context-factory-' . $salesChannelId;
    }
}
