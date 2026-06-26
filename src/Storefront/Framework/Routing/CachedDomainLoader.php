<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Routing;

use Shopware\Core\Framework\Adapter\Cache\CacheValueCompressor;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Framework\Routing\Struct\DomainCollection;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * @phpstan-import-type Domain from AbstractDomainLoader
 */
#[Package('framework')]
class CachedDomainLoader extends AbstractDomainLoader
{
    /**
     * @deprecated tag:v6.8.0 - reason:becomes-unused - Will be removed together with the deprecated load(), use DOMAIN_COLLECTION_CACHE_KEY instead
     */
    final public const CACHE_KEY = 'routing-domains';

    final public const DOMAIN_COLLECTION_CACHE_KEY = 'routing-domain-collection';

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
     * @deprecated tag:v6.8.0 - reason:becomes-unused - Will be removed, use loadDomains() instead
     *
     * @return array<string, Domain>
     */
    public function load(): array
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0', 'loadDomains()')
        );

        $value = $this->cache->get(self::CACHE_KEY, fn (ItemInterface $item) => CacheValueCompressor::compress(
            $this->getDecorated()->load()
        ));

        /** @var array<string, Domain> $value */
        $value = CacheValueCompressor::uncompress($value);

        return $value;
    }

    public function loadDomains(): DomainCollection
    {
        $value = $this->cache->get(self::DOMAIN_COLLECTION_CACHE_KEY, fn (ItemInterface $item) => CacheValueCompressor::compress(
            $this->getDecorated()->loadDomains()
        ));

        /** @var DomainCollection $value */
        $value = CacheValueCompressor::uncompress($value);

        return $value;
    }
}
