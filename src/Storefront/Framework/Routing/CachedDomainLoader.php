<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Routing;

use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Adapter\Cache\CacheCompressor;
use Symfony\Component\Cache\Adapter\TagAwareAdapterInterface;

class CachedDomainLoader extends AbstractDomainLoader
{
    public const CACHE_KEY = 'routing-domains';

    private AbstractDomainLoader $decorated;

    private TagAwareAdapterInterface $cache;

    private LoggerInterface $logger;

    public function __construct(AbstractDomainLoader $decorated, TagAwareAdapterInterface $cache, LoggerInterface $logger)
    {
        $this->decorated = $decorated;
        $this->cache = $cache;
        $this->logger = $logger;
    }

    public function getDecorated(): AbstractDomainLoader
    {
        return $this->decorated;
    }

    public function load(): array
    {
        $item = $this->cache->getItem(self::CACHE_KEY);

        try {
            if ($item->isHit() && $item->get()) {
                $this->logger->info('cache-hit: ' . self::CACHE_KEY);

                return CacheCompressor::uncompress($item);
            }
        } catch (\Throwable $e) {
            $this->logger->error($e->getMessage());
        }

        $this->logger->info('cache-miss: ' . self::CACHE_KEY);

        $domains = $this->getDecorated()->load();

        $item = CacheCompressor::compress($item, $domains);

        $this->cache->save($item);

        return $domains;
    }
}
