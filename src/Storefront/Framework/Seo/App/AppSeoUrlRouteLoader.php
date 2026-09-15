<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Seo\App;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\TableNotFoundException;
use Shopware\Core\Content\Seo\ConfiguredEntitySeoUrlRoute;
use Shopware\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteLoaderInterface;
use Shopware\Core\Framework\Adapter\Cache\CacheCompressor;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Cache\Adapter\TagAwareAdapterInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * @internal
 *
 * @phpstan-type AppSeoUrlRouteRow array{appId: string, routeName: string, hook: string, entityName: string|null, defaultTemplate: string|null, paths: array<string, string>}
 */
#[Package('inventory')]
class AppSeoUrlRouteLoader implements SeoUrlRouteLoaderInterface, EventSubscriberInterface, ResetInterface
{
    final public const CACHE_KEY = 'shopware-app-seo-url-routes';

    /**
     * @var list<AppSeoUrlRouteRow>|null
     */
    private ?array $routes = null;

    public function __construct(
        private readonly Connection $connection,
        private readonly DefinitionInstanceRegistry $definitionRegistry,
        private readonly TagAwareAdapterInterface $cache,
    ) {
    }

    /**
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'app.written' => 'invalidateCache',
            'app_seo_url_route.written' => 'invalidateCache',
        ];
    }

    public function load(): iterable
    {
        foreach ($this->getEntityRoutes() as $route) {
            if (!$this->definitionRegistry->has($route['entityName'])) {
                continue;
            }

            yield new ConfiguredEntitySeoUrlRoute(new AppSeoUrlRoute(
                $this->definitionRegistry->getByEntityName($route['entityName']),
                $route['routeName'],
                $route['hook'],
                $route['defaultTemplate'],
            ));
        }
    }

    /**
     * @return list<array{appId: string, routeName: string, hook: string, entityName: string, defaultTemplate: string}>
     */
    public function getEntityRoutes(?string $appId = null): array
    {
        $routes = [];

        foreach ($this->getRoutes() as $route) {
            if ($route['entityName'] === null || $route['defaultTemplate'] === null) {
                continue;
            }

            if ($appId !== null && $route['appId'] !== $appId) {
                continue;
            }

            $routes[] = [
                'appId' => $route['appId'],
                'routeName' => $route['routeName'],
                'hook' => $route['hook'],
                'entityName' => $route['entityName'],
                'defaultTemplate' => $route['defaultTemplate'],
            ];
        }

        return $routes;
    }

    /**
     * @return list<array{appId: string, routeName: string, hook: string, paths: non-empty-array<string, string>}>
     */
    public function getStaticRoutes(?string $appId = null): array
    {
        $routes = [];

        foreach ($this->getRoutes() as $route) {
            if ($route['entityName'] !== null || $route['paths'] === []) {
                continue;
            }

            if ($appId !== null && $route['appId'] !== $appId) {
                continue;
            }

            $routes[] = [
                'appId' => $route['appId'],
                'routeName' => $route['routeName'],
                'hook' => $route['hook'],
                'paths' => $route['paths'],
            ];
        }

        return $routes;
    }

    public function invalidateCache(): void
    {
        $this->reset();

        $this->cache->deleteItem(self::CACHE_KEY);
    }

    public function reset(): void
    {
        $this->routes = null;
    }

    /**
     * @return list<AppSeoUrlRouteRow>
     */
    private function getRoutes(): array
    {
        if ($this->routes !== null) {
            return $this->routes;
        }

        $cacheItem = $this->cache->getItem(self::CACHE_KEY);
        if ($cacheItem->isHit() && $cacheItem->get()) {
            /** @var list<AppSeoUrlRouteRow> $routes */
            $routes = CacheCompressor::uncompress($cacheItem);

            return $this->routes = $routes;
        }

        try {
            $routes = $this->fetch();
        } catch (TableNotFoundException) {
            return [];
        }

        $this->cache->save(CacheCompressor::compress($cacheItem, $routes));

        return $this->routes = $routes;
    }

    /**
     * @return list<AppSeoUrlRouteRow>
     */
    private function fetch(): array
    {
        /** @var list<array{appId: string, routeName: string, hook: string, entityName: string|null, defaultTemplate: string|null, paths: string|null}> $rows */
        $rows = $this->connection->fetchAllAssociative(
            'SELECT LOWER(HEX(`route`.`app_id`)) AS `appId`,
                    `route`.`route_name` AS `routeName`,
                    `route`.`hook` AS `hook`,
                    `route`.`entity_name` AS `entityName`,
                    `route`.`default_template` AS `defaultTemplate`,
                    `route`.`paths` AS `paths`
             FROM `app_seo_url_route` AS `route`
             INNER JOIN `app` ON `app`.`id` = `route`.`app_id` AND `app`.`active` = 1
             ORDER BY `route`.`route_name`'
        );

        $routes = [];

        foreach ($rows as $row) {
            $routes[] = [
                'appId' => $row['appId'],
                'routeName' => $row['routeName'],
                'hook' => $row['hook'],
                'entityName' => $row['entityName'],
                'defaultTemplate' => $row['defaultTemplate'],
                'paths' => $this->decodePaths($row['paths']),
            ];
        }

        return $routes;
    }

    /**
     * @return array<string, string>
     */
    private function decodePaths(?string $paths): array
    {
        if ($paths === null || $paths === '') {
            return [];
        }

        $decoded = json_decode($paths, true);

        if (!\is_array($decoded)) {
            return [];
        }

        /** @var array<string, string> $decoded */
        return $decoded;
    }
}
