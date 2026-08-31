<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Routing;

use Shopware\Core\Framework\Adapter\Cache\CacheValueCompressor;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Framework\Routing\Struct\DomainCollection;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * @phpstan-import-type Domain from AbstractDomainLoader
 */
#[Package('discovery')]
class CachedDomainLoader extends AbstractDomainLoader implements ResetInterface
{
    /**
     * @deprecated tag:v6.8.0 - reason:becomes-unused - Will be removed together with the deprecated load(), use DOMAIN_COLLECTION_CACHE_KEY instead
     */
    final public const CACHE_KEY = 'routing-domains';

    final public const DOMAIN_COLLECTION_CACHE_KEY = 'routing-domain-collection';

    /**
     * @var array<string, Domain>|null
     */
    private ?array $domains = null;

    private ?DomainCollection $domainCollection = null;

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

        if ($this->domains !== null) {
            return $this->domains;
        }

        $fresh = null;

        $value = $this->cache->get(self::CACHE_KEY, function (ItemInterface $item) use (&$fresh): string {
            $fresh = $this->getDecorated()->load();

            return CacheValueCompressor::compress($fresh);
        });

        // the domains were loaded in this call, return them directly instead of
        // uncompressing the cache payload that was just compressed from them
        if ($fresh !== null) {
            return $this->domains = $fresh;
        }

        /** @var array<string, Domain> $value */
        $value = CacheValueCompressor::uncompress($value);

        return $this->domains = $value;
    }

    public function loadDomains(): DomainCollection
    {
        if ($this->domainCollection !== null) {
            return $this->domainCollection;
        }

        $fresh = null;

        $value = $this->cache->get(self::DOMAIN_COLLECTION_CACHE_KEY, function (ItemInterface $item) use (&$fresh): string {
            $fresh = $this->getDecorated()->loadDomains();

            return CacheValueCompressor::compress($fresh);
        });

        // the domains were loaded in this call, return them directly instead of
        // uncompressing the cache payload that was just compressed from them
        if ($fresh instanceof DomainCollection) {
            return $this->domainCollection = $fresh;
        }

        /** @var DomainCollection $value */
        $value = CacheValueCompressor::uncompress($value);

        return $this->domainCollection = $value;
    }

    public function reset(): void
    {
        $this->domains = null;
        $this->domainCollection = null;
    }
}
