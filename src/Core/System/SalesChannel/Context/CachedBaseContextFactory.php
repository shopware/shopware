<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\Context;

use Shopware\Core\Framework\Adapter\Cache\AbstractCacheTracer;
use Shopware\Core\Framework\Adapter\Cache\CacheValueCompressor;
use Shopware\Core\System\SalesChannel\BaseContext;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * @package core
 *
 * @internal
 */
class CachedBaseContextFactory extends AbstractBaseContextFactory
{
    private AbstractBaseContextFactory $decorated;

    private CacheInterface $cache;

    /**
     * @var AbstractCacheTracer<SalesChannelContext>
     */
    private AbstractCacheTracer $tracer;

    /**
     * @param AbstractCacheTracer<SalesChannelContext> $tracer
     */
    public function __construct(
        AbstractBaseContextFactory $decorated,
        CacheInterface $cache,
        AbstractCacheTracer $tracer
    ) {
        $this->decorated = $decorated;
        $this->cache = $cache;
        $this->tracer = $tracer;
    }

    public function getDecorated(): AbstractBaseContextFactory
    {
        return $this->decorated;
    }

    public function create(string $salesChannelId, array $options = []): BaseContext
    {
        if (isset($options[SalesChannelContextService::ORIGINAL_CONTEXT])) {
            return $this->getDecorated()->create($salesChannelId, $options);
        }
        if (isset($options[SalesChannelContextService::PERMISSIONS])) {
            return $this->getDecorated()->create($salesChannelId, $options);
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

        $key = implode('-', [$name, md5(json_encode($keys, \JSON_THROW_ON_ERROR))]);

        $value = $this->cache->get($key, function (ItemInterface $item) use ($name, $salesChannelId, $options) {
            $context = $this->tracer->trace($name, function () use ($salesChannelId, $options) {
                return $this->getDecorated()->create($salesChannelId, $options);
            });

            $keys = array_unique(array_merge(
                $this->tracer->get($name),
                [$name, CachedSalesChannelContextFactory::ALL_TAG]
            ));

            $item->tag($keys);

            return CacheValueCompressor::compress($context);
        });

        return CacheValueCompressor::uncompress($value);
    }

    public static function buildName(string $salesChannelId): string
    {
        return 'base-context-factory-' . $salesChannelId;
    }
}
