<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Routing;

use Shopware\Core\Framework\Adapter\Cache\CacheValueCompressor;
use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Framework\Routing\Struct\DomainStruct;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

#[Package('framework')]
class CachedDomainLoader extends AbstractDomainLoader
{
    final public const CACHE_KEY = 'routing-domains';

    /**
     * @internal
     */
    public function __construct(
        private readonly AbstractDomainLoader $decorated,
        private readonly CacheInterface $cache
    ) {
    }

    public function getDecorated(): AbstractDomainLoader
    {
        return $this->decorated;
    }

    /**
     * @return array<string, DomainStruct>
     */
    public function load(): array
    {
        $value = $this->cache->get(self::CACHE_KEY, fn (ItemInterface $item) => CacheValueCompressor::compress(
            $this->getDecorated()->load()
        ));

        /** @var array<string, DomainStruct> $value */
        $value = CacheValueCompressor::uncompress($value);

        return $value;
    }
}
