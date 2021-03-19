<?php declare(strict_types=1);

namespace Shopware\Core\Content\Seo;

use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Adapter\Cache\CacheCompressor;
use Symfony\Component\Cache\Adapter\TagAwareAdapterInterface;

class CachedSeoResolver extends AbstractSeoResolver
{
    private AbstractSeoResolver $decorated;

    private TagAwareAdapterInterface $cache;

    private LoggerInterface $logger;

    public function __construct(AbstractSeoResolver $decorated, TagAwareAdapterInterface $cache, LoggerInterface $logger)
    {
        $this->decorated = $decorated;
        $this->cache = $cache;
        $this->logger = $logger;
    }

    public function getDecorated(): AbstractSeoResolver
    {
        return $this->decorated;
    }

    public function resolve(string $languageId, string $salesChannelId, string $pathInfo): array
    {
        $name = 'seo-resolver';
        $key = md5(implode('-', [
            $name,
            $languageId,
            $salesChannelId,
            $pathInfo,
        ]));

        $item = $this->cache->getItem($key);

        try {
            if ($item->isHit() && $item->get()) {
                $this->logger->info('cache-hit: ' . $name);

                return CacheCompressor::uncompress($item);
            }
        } catch (\Throwable $e) {
            $this->logger->error($e->getMessage());
        }

        $this->logger->info('cache-miss: ' . $name);

        $resolved = $this->getDecorated()->resolve($languageId, $salesChannelId, $pathInfo);

        $item = CacheCompressor::compress($item, $resolved);

        $item->tag([self::buildName($pathInfo)]);

        $this->cache->save($item);

        return $resolved;
    }

    public static function buildName(string $pathInfo): string
    {
        return 'path-info-' . md5($pathInfo);
    }
}
