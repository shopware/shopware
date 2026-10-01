<?php declare(strict_types=1);

namespace Shopware\Core\Content\Seo\SeoUrlRoute;

use Shopware\Core\Framework\Log\Package;

#[Package('inventory')]
class SeoUrlRouteRegistry
{
    /**
     * @var array<string, SeoUrlRouteInterface>
     */
    private array $seoUrlRoutes = [];

    /**
     * @var array<string, list<SeoUrlRouteInterface>>
     */
    private array $definitionToRoutes = [];

    /**
     * @internal
     *
     * @param iterable<SeoUrlRouteInterface> $seoUrlRoutes
     * @param iterable<SeoUrlRouteLoaderInterface> $loaders
     */
    public function __construct(
        iterable $seoUrlRoutes,
        private readonly iterable $loaders = [],
    ) {
        foreach ($seoUrlRoutes as $seoUrlRoute) {
            $config = $seoUrlRoute->getConfig();

            $route = $config->getRouteName();
            $this->seoUrlRoutes[$route] = $seoUrlRoute;
            $entityName = $config->getDefinition()->getEntityName();
            $this->definitionToRoutes[$entityName][] = $seoUrlRoute;
        }
    }

    /**
     * @return iterable<string, SeoUrlRouteInterface>
     */
    public function getSeoUrlRoutes(): iterable
    {
        return [...$this->seoUrlRoutes, ...$this->loadRuntimeRoutes()];
    }

    public function findByRouteName(string $routeName): ?SeoUrlRouteInterface
    {
        return $this->seoUrlRoutes[$routeName] ?? $this->loadRuntimeRoutes()[$routeName] ?? null;
    }

    /**
     * @return SeoUrlRouteInterface[]
     */
    public function findByDefinition(string $definitionName): array
    {
        $routes = $this->definitionToRoutes[$definitionName] ?? [];

        foreach ($this->loadRuntimeRoutes() as $route) {
            if ($route->getConfig()->getDefinition()->getEntityName() === $definitionName) {
                $routes[] = $route;
            }
        }

        return $routes;
    }

    /**
     * @return array<string, SeoUrlRouteInterface>
     */
    private function loadRuntimeRoutes(): array
    {
        $routes = [];

        foreach ($this->loaders as $loader) {
            foreach ($loader->load() as $route) {
                $routeName = $route->getConfig()->getRouteName();

                if (isset($this->seoUrlRoutes[$routeName]) || isset($routes[$routeName])) {
                    continue;
                }

                $routes[$routeName] = $route;
            }
        }

        return $routes;
    }
}
