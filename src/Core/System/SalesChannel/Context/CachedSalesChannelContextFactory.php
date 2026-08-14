<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\Context;

use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\Framework\Adapter\Cache\CacheValueCompressor;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Hasher;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

#[Package('framework')]
class CachedSalesChannelContextFactory extends AbstractSalesChannelContextFactory
{
    final public const ALL_TAG = 'sales-channel-context';

    /**
     * @internal
     */
    public function __construct(
        private readonly AbstractSalesChannelContextFactory $decorated,
        private readonly CacheInterface $cache,
        private readonly AtsContextCacheTrace $atsContextCacheTrace,
    ) {
    }

    public function getDecorated(): AbstractSalesChannelContextFactory
    {
        return $this->decorated;
    }

    public function create(string $token, string $salesChannelId, array $options = []): SalesChannelContext
    {
        // ATS changes sales channel base data within one application instance; skip the cache so subsequent requests see the setup immediately.
        if (EnvironmentHelper::getVariable('ATS_RUNNING') === '1' && EnvironmentHelper::getVariable('ATS_CACHE_TRACE') !== '1') {
            return $this->decorated->create($token, $salesChannelId, $options);
        }

        $name = self::buildName($salesChannelId);

        if (!$this->isCacheable($options)) {
            return $this->getDecorated()->create($token, $salesChannelId, $options);
        }

        ksort($options);

        $key = implode('-', [$name, Hasher::hash($options)]);
        $cacheMiss = false;
        $fresh = null;

        $value = $this->cache->get($key, function (ItemInterface $item) use ($name, $token, $salesChannelId, $options, &$cacheMiss, &$fresh) {
            $cacheMiss = true;
            $item->tag([$name, self::ALL_TAG]);

            return CacheValueCompressor::compress($fresh = $this->decorated->create($token, $salesChannelId, $options));
        });

        // The context was built in this call, return it directly instead of uncompressing the cache payload that was just compressed from it.
        if ($fresh instanceof SalesChannelContext) {
            $this->atsContextCacheTrace->cacheAccess('full', $cacheMiss, $fresh->getTaxRules()->count());

            return $fresh;
        }

        $context = CacheValueCompressor::uncompress($value);

        if (!$context instanceof SalesChannelContext) {
            return $this->getDecorated()->create($token, $salesChannelId, $options);
        }

        $this->atsContextCacheTrace->cacheAccess('full', $cacheMiss, $context->getTaxRules()->count());

        $context->assign(['token' => $token]);

        return $context;
    }

    public static function buildName(string $salesChannelId): string
    {
        return 'context-factory-' . $salesChannelId;
    }

    /**
     * @param array<string, mixed> $options
     */
    private function isCacheable(array $options): bool
    {
        return !isset($options[SalesChannelContextService::CUSTOMER_ID])
            && !isset($options[SalesChannelContextService::BILLING_ADDRESS_ID])
            && !isset($options[SalesChannelContextService::SHIPPING_ADDRESS_ID]);
    }
}
