<?php declare(strict_types=1);

namespace Shopware\Core\Content\Seo;

use Shopware\Core\Framework\Adapter\Cache\CacheValueCompressor;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class CachedSeoResolver extends AbstractSeoResolver
{
    private AbstractSeoResolver $decorated;

    private CacheInterface $cache;

    public function __construct(AbstractSeoResolver $decorated, CacheInterface $cache)
    {
        $this->decorated = $decorated;
        $this->cache = $cache;
    }

    public function getDecorated(): AbstractSeoResolver
    {
        return $this->decorated;
    }

    public function resolve(string $languageId, string $salesChannelId, string $pathInfo): array
    {
        $key = 'seo-resolver-' . md5(implode('-', [$languageId, $salesChannelId, $pathInfo]));

        $value = $this->cache->get($key, function (ItemInterface $item) use ($languageId, $salesChannelId, $pathInfo) {
            $resolved = $this->getDecorated()->resolve($languageId, $salesChannelId, $pathInfo);

            $item->tag([self::buildName($pathInfo)]);

            return CacheValueCompressor::compress($resolved);
        });

        return CacheValueCompressor::uncompress($value);
    }

    public static function buildName(string $pathInfo): string
    {
        return 'path-info-' . md5($pathInfo);
    }
}
