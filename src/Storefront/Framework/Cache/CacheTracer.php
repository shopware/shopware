<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Cache;

use Shopware\Core\Framework\Adapter\Cache\AbstractCacheTracer;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Theme\ThemeConfigValueAccessor;

/**
 * @deprecated tag:v6.7.0 - Will be removed, use AddCacheTagEvent instead
 *
 * @internal
 *
 * @extends AbstractCacheTracer<mixed|null>
 */
#[Package('core')]
class CacheTracer extends AbstractCacheTracer
{
    /**
     * @internal
     *
     * @param AbstractCacheTracer<mixed|null> $decorated
     */
    public function __construct(
        private readonly AbstractCacheTracer $decorated,
        private readonly ThemeConfigValueAccessor $themeConfigAccessor
    ) {
    }

    public function getDecorated(): AbstractCacheTracer
    {
        Feature::triggerDeprecationOrThrow(
            'v6.7.0.0',
            Feature::deprecatedMethodMessage(self::class, __FUNCTION__, 'v6.7.0.0')
        );

        return $this->decorated;
    }

    public function trace(string $key, \Closure $param)
    {
        Feature::triggerDeprecationOrThrow(
            'v6.7.0.0',
            Feature::deprecatedMethodMessage(self::class, __FUNCTION__, 'v6.7.0.0')
        );

        return $this->themeConfigAccessor->trace($key, fn () => $this->getDecorated()->trace($key, $param));
    }

    public function get(string $key): array
    {
        Feature::triggerDeprecationOrThrow(
            'v6.7.0.0',
            Feature::deprecatedMethodMessage(self::class, __FUNCTION__, 'v6.7.0.0')
        );

        return array_unique(array_merge(
            $this->themeConfigAccessor->getTrace($key),
            $this->getDecorated()->get($key)
        ));
    }
}
