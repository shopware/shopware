<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\Context;

use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\Framework\Adapter\Cache\CacheValueCompressor;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Hasher;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

#[Package('framework')]
class CachedSalesChannelContextFactory extends AbstractSalesChannelContextFactory
{
    final public const ALL_TAG = 'sales-channel-context';

    /**
     * @internal
     */
    public function __construct(
        private readonly AbstractSalesChannelContextFactory $decorated,
        private readonly InvalidationRaceAwareCache $cache,
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
        $tags = [$name, self::ALL_TAG];
        $cacheMiss = false;
        $fresh = null;

        $value = $this->cache->get($key, $tags, function () use ($key, $token, $salesChannelId, $options, &$cacheMiss, &$fresh): string {
            $cacheMiss = true;

            $this->atsContextCacheTrace->cacheBuildStarted('full', $key);

            $fresh = $this->decorated->create($token, $salesChannelId, $options);
            $this->atsContextCacheTrace->cacheBuildCompleted('full', $key, $fresh->getTaxRules()->count());

            return CacheValueCompressor::compress($fresh);
        });

        // The context was built in this call, return it directly instead of uncompressing the cache payload that was just compressed from it.
        if ($fresh instanceof SalesChannelContext) {
            $this->atsContextCacheTrace->cacheAccess('full', $key, $cacheMiss, $fresh->getTaxRules()->count());

            return $fresh;
        }

        $context = CacheValueCompressor::uncompress($value);

        if (!$context instanceof SalesChannelContext) {
            return $this->getDecorated()->create($token, $salesChannelId, $options);
        }

        $this->atsContextCacheTrace->cacheAccess('full', $key, $cacheMiss, $context->getTaxRules()->count());

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
