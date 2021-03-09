<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Cache;

use Shopware\Core\Framework\Adapter\Cache\AbstractCacheTracer;
use Shopware\Storefront\Theme\ThemeConfigValueAccessor;

class CacheTracer extends AbstractCacheTracer
{
    private AbstractCacheTracer $decorated;

    private ThemeConfigValueAccessor $themeConfigAccessor;

    public function __construct(AbstractCacheTracer $decorated, ThemeConfigValueAccessor $themeConfigAccessor)
    {
        $this->decorated = $decorated;
        $this->themeConfigAccessor = $themeConfigAccessor;
    }

    public function getDecorated(): AbstractCacheTracer
    {
        return $this->decorated;
    }

    public function trace(string $key, \Closure $param)
    {
        return $this->themeConfigAccessor->trace($key, function () use ($key, $param) {
            return $this->getDecorated()->trace($key, $param);
        });
    }

    public function get(string $key): array
    {
        return array_unique(array_merge(
            $this->themeConfigAccessor->getTrace($key),
            $this->getDecorated()->get($key)
        ));
    }
}
