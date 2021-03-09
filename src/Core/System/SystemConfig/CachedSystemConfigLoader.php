<?php declare(strict_types=1);

namespace Shopware\Core\System\SystemConfig;

use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Adapter\Cache\CacheCompressor;
use Shopware\Core\Framework\Feature;
use Symfony\Component\Cache\Adapter\TagAwareAdapterInterface;

class CachedSystemConfigLoader extends AbstractSystemConfigLoader
{
    public const CACHE_TAG = 'system-config';

    private AbstractSystemConfigLoader $decorated;

    private TagAwareAdapterInterface $cache;

    private LoggerInterface $logger;

    public function __construct(AbstractSystemConfigLoader $decorated, TagAwareAdapterInterface $cache, LoggerInterface $logger)
    {
        $this->decorated = $decorated;
        $this->cache = $cache;
        $this->logger = $logger;
    }

    public function getDecorated(): AbstractSystemConfigLoader
    {
        return $this->decorated;
    }

    public function load(?string $salesChannelId): array
    {
        if (!Feature::isActive('FEATURE_NEXT_10514')) {
            return $this->getDecorated()->load($salesChannelId);
        }

        $key = 'system-config-' . $salesChannelId;

        $item = $this->cache->getItem($key);

        if ($item->isHit() && $item->get()) {
            $this->logger->info('cache-hit: ' . $key);

            return CacheCompressor::uncompress($item);
        }

        $this->logger->info('cache-miss: ' . $key);

        $config = $this->getDecorated()->load($salesChannelId);

        $item = CacheCompressor::compress($item, $config);
        $item->tag([self::CACHE_TAG]);

        $this->cache->save($item);

        return $config;
    }
}
