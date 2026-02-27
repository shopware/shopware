<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\SalesChannel\Routing;

use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Config\Loader\Loader;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * @internal
 *
 * @final
 */
#[Package('discovery')]
class ContentRouteLoader extends Loader
{
    private const ROUTE_TYPE = 'content_system';

    private bool $isLoaded = false;

    /**
     * @param list<ContentRouteDefinition> $routeDefinitions
     */
    public function __construct(
        private readonly array $routeDefinitions,
    ) {
        parent::__construct();
    }

    public function load(mixed $resource, ?string $type = null): RouteCollection
    {
        if ($this->isLoaded) {
            throw ContentSystemException::routesAlreadyLoaded();
        }

        $routes = new RouteCollection();

        foreach ($this->routeDefinitions as $definition) {
            $route = new Route($definition->path);
            $route->setMethods([Request::METHOD_GET]);
            $route->setDefaults($definition->defaults);
            $route->setRequirements($definition->requirements);

            $routes->add($definition->name, $route);
        }

        $this->isLoaded = true;

        return $routes;
    }

    public function supports(mixed $resource, ?string $type = null): bool
    {
        return $type === self::ROUTE_TYPE;
    }
}
