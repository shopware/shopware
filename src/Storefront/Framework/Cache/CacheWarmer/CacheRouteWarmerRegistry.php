<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Cache\CacheWarmer;

use Shopware\Core\Framework\Log\Package;

#[Package('storefront')]
class CacheRouteWarmerRegistry
{
    /**
     * @var CacheRouteWarmer[]
     */
    private $warmers;

    /**
     * @internal
     */
    public function __construct(iterable $routes)
    {
        $this->warmers = $routes;
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
            if (\get_class($warmer) === $class) {
                return $warmer;
            }
        }

        return null;
    }
}
