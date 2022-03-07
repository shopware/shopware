<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Routing;

use Shopware\Core\Framework\Adapter\Cache\CacheValueCompressor;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class CachedDomainLoader extends AbstractDomainLoader
{
    public const CACHE_KEY = 'routing-domains';

    private AbstractDomainLoader $decorated;

    private CacheInterface $cache;

    public function __construct(AbstractDomainLoader $decorated, CacheInterface $cache)
    {
        $this->decorated = $decorated;
        $this->cache = $cache;
    }

    public function getDecorated(): AbstractDomainLoader
    {
        return $this->decorated;
    }

    public function load(): array
    {
        $value = $this->cache->get(self::CACHE_KEY, function (ItemInterface $item) {
            return CacheValueCompressor::compress(
                $this->getDecorated()->load()
            );
        });

        return CacheValueCompressor::uncompress($value);
    }
}
