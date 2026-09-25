<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Seo\App;

use Shopware\Core\Framework\Adapter\Cache\CacheValueCompressor;
use Shopware\Core\Framework\App\AppEvents;
use Shopware\Core\Framework\App\Feature\AppFeatureStorage;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * @internal
 */
#[Package('inventory')]
class AppSeoUrlRouteProvider implements EventSubscriberInterface, ResetInterface
{
    final public const CACHE_KEY = 'app-seo-url-routes';

    /**
     * @var list<AppEntitySeoUrlConfig>|null
     */
    private ?array $routes = null;

    public function __construct(
        private readonly AppFeatureStorage $storage,
        private readonly CacheInterface $cache,
    ) {
    }

    /**
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            AppEvents::APP_WRITTEN_EVENT => 'invalidate',
            AppEvents::APP_DELETED_EVENT => 'invalidate',
        ];
    }

    /**
     * @return list<AppEntitySeoUrlConfig>
     */
    public function getEntityRoutes(): array
    {
        if ($this->routes !== null) {
            return $this->routes;
        }

        $fresh = null;

        $value = $this->cache->get(self::CACHE_KEY, function (ItemInterface $item) use (&$fresh) {
            $item->tag([self::CACHE_KEY]);

            $fresh = $this->fetch();

            return CacheValueCompressor::compress($fresh);
        });

        if ($fresh !== null) {
            return $this->routes = $fresh;
        }

        return $this->routes = CacheValueCompressor::uncompress($value);
    }

    public function invalidate(): void
    {
        $this->reset();
        $this->cache->delete(self::CACHE_KEY);
    }

    public function reset(): void
    {
        $this->routes = null;
    }

    /**
     * @return list<AppEntitySeoUrlConfig>
     */
    private function fetch(): array
    {
        $routes = [];

        foreach ($this->storage->forActiveApps(AppEntitySeoUrlConfig::class) as $feature) {
            $routes[] = $feature->config;
        }

        return $routes;
    }
}
