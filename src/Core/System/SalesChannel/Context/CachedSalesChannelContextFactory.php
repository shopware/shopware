<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\Context;

use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Adapter\Cache\AbstractCacheTracer;
use Shopware\Core\Framework\Adapter\Cache\CacheCompressor;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Cache\Adapter\TagAwareAdapterInterface;

class CachedSalesChannelContextFactory extends AbstractSalesChannelContextFactory
{
    public const ALL_TAG = 'sales-channel-context';

    private AbstractSalesChannelContextFactory $decorated;

    private TagAwareAdapterInterface $cache;

    /**
     * @var AbstractCacheTracer<SalesChannelContext>
     */
    private AbstractCacheTracer $tracer;

    private LoggerInterface $logger;

    /**
     * @param AbstractCacheTracer<SalesChannelContext> $tracer
     */
    public function __construct(
        AbstractSalesChannelContextFactory $decorated,
        TagAwareAdapterInterface $cache,
        AbstractCacheTracer $tracer,
        LoggerInterface $logger
    ) {
        $this->decorated = $decorated;
        $this->cache = $cache;
        $this->tracer = $tracer;
        $this->logger = $logger;
    }

    public function getDecorated(): AbstractSalesChannelContextFactory
    {
        return $this->decorated;
    }

    public function create(string $token, string $salesChannelId, array $options = []): SalesChannelContext
    {
        $name = self::buildName($salesChannelId);

        if (!$this->isCacheable($options)) {
            $this->logger->info('cache-miss: ' . $name);

            return $this->getDecorated()->create($token, $salesChannelId, $options);
        }

        ksort($options);

        $key = md5(implode('-', [
            $name,
            json_encode($options),
        ]));

        $item = $this->cache->getItem($key);

        try {
            if ($item->isHit() && $item->get()) {
                $this->logger->info('cache-hit: ' . $name);

                /** @var SalesChannelContext $context */
                $context = CacheCompressor::uncompress($item);
                $context->assign(['token' => $token]);

                return $context;
            }
        } catch (\Throwable $e) {
            $this->logger->error($e->getMessage());
        }

        $this->logger->info('cache-miss: ' . $name);

        $context = $this->tracer->trace($name, function () use ($token, $salesChannelId, $options) {
            return $this->getDecorated()->create($token, $salesChannelId, $options);
        });

        $keys = array_unique(array_merge(
            $this->tracer->get($name),
            [$name, self::ALL_TAG]
        ));

        $item = CacheCompressor::compress($item, $context);
        $item->tag($keys);

        $this->cache->save($item);

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
