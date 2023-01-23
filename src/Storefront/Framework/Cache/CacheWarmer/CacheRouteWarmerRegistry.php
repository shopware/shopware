<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Cache\CacheWarmer;

/**
 * @package storefront
 */
class CacheRouteWarmerRegistry
{
    /**
     * @internal
     *
     * @param CacheRouteWarmer[] $warmers
     */
    public function __construct(private readonly iterable $warmers)
    {
    }

    /**
     * @return iterable|CacheRouteWarmer[]
     */
    public function getWarmers(): iterable
    {
        return $this->warmers;
    }

    public function getWarmer(string $class): ?CacheRouteWarmer
    {
        foreach ($this->getWarmers() as $warmer) {
            if ($warmer::class === $class) {
                return $warmer;
            }
        }

        return null;
    }
}
