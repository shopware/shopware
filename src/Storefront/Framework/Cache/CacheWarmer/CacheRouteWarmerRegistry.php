<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Cache\CacheWarmer;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;

/**
 * @deprecated tag:v6.6.0 - Will be removed, use site crawlers for real cache warming
 */
#[Package('core')]
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
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.6.0.0')
        );

        return $this->warmers;
    }

    public function getWarmer(string $class): ?CacheRouteWarmer
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.6.0.0')
        );

        foreach ($this->getWarmers() as $warmer) {
            if ($warmer::class === $class) {
                return $warmer;
            }
        }

        return null;
    }
}
