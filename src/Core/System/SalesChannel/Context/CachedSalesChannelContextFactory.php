<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\Context;

use Shopware\Core\Framework\Adapter\Cache\AbstractCacheTracer;
use Shopware\Core\Framework\Adapter\Cache\CacheValueCompressor;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class CachedSalesChannelContextFactory extends AbstractSalesChannelContextFactory
{
    public const ALL_TAG = 'sales-channel-context';

    private AbstractSalesChannelContextFactory $decorated;

    private CacheInterface $cache;

    /**
     * @var AbstractCacheTracer<SalesChannelContext>
     */
    private AbstractCacheTracer $tracer;

    /**
     * @internal
     *
     * @param AbstractCacheTracer<SalesChannelContext> $tracer
     */
    public function __construct(
        AbstractSalesChannelContextFactory $decorated,
        CacheInterface $cache,
        AbstractCacheTracer $tracer
    ) {
        $this->decorated = $decorated;
        $this->cache = $cache;
        $this->tracer = $tracer;
    }

    public function getDecorated(): AbstractSalesChannelContextFactory
    {
        return $this->decorated;
    }

    public function create(string $token, string $salesChannelId, array $options = []): SalesChannelContext
    {
        $name = self::buildName($salesChannelId);

        if (!$this->isCacheable($options)) {
            return $this->getDecorated()->create($token, $salesChannelId, $options);
        }

        ksort($options);

        $key = implode('-', [$name, md5(json_encode($options, \JSON_THROW_ON_ERROR))]);

        $value = $this->cache->get($key, function (ItemInterface $item) use ($name, $token, $salesChannelId, $options) {
            $context = $this->tracer->trace($name, function () use ($token, $salesChannelId, $options) {
                return $this->getDecorated()->create($token, $salesChannelId, $options);
            });

            $keys = array_unique(array_merge(
                $this->tracer->get($name),
                [$name, self::ALL_TAG]
            ));

            $item->tag($keys);

            return CacheValueCompressor::compress($context);
        });

        $context = CacheValueCompressor::uncompress($value);

        $context->assign(['token' => $token]);

        return $context;
    }

    public static function buildName(string $salesChannelId): string
    {
        return 'context-factory-' . $salesChannelId;
    }

    private function isCacheable(array $options): bool
    {
        return !isset($options[SalesChannelContextService::CUSTOMER_ID])
            && !isset($options[SalesChannelContextService::BILLING_ADDRESS_ID])
            && !isset($options[SalesChannelContextService::SHIPPING_ADDRESS_ID]);
    }
}
