<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Route;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Routing\RouterInterface;

/**
 * @internal
 */
#[Package('core')]
class ApiRouteInfoResolver
{
    /**
     * @internal
     */
    public function __construct(private readonly RouterInterface $router)
    {
    }

    /**
     * This method is SLOW and usage in recurrently used code should be avoided.
     *
     * @return RouteInfo[]
     */
    public function getApiRoutes(string $apiScope): array
    {
        $routes = [];
        foreach ($this->router->getRouteCollection()->all() as $route) {
            $routeScope = $route->getDefaults()['_routeScope'] ?? [];
            if (!\in_array($apiScope, $routeScope, true)) {
                continue;
            }

            $routePath = (string) ($route->getOption(ApiRouteLoader::DYNAMIC_RESOURCE_ROOT_PATH) ?? $route->getPath());
            $routes[$routePath] = array_merge($routes[$routePath] ?? [], $route->getMethods());
        }

        return array_map(
            fn (string $path, array $methods) => new RouteInfo(path: $path, methods: $methods),
            array_keys($routes),
            array_values($routes)
        );
    }
}
