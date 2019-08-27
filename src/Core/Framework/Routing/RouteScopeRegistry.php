<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Routing;

/**
 * Contains all registered RouteScopes in the system
 */
class RouteScopeRegistry
{
    /**
     * @var RouteScopeInterface[]
     */
    private $routeScopes;

    public function __construct(iterable $routeScopes)
    {
        $this->routeScopes = $routeScopes;
    }

    /**
     * @return RouteScopeInterface[]|iterable
     */
    public function getRouteScopes(): iterable
    {
        return $this->routeScopes;
    }

    public function getRouteScope(string $id): ?RouteScopeInterface
    {
        foreach ($this->routeScopes as $routeScope) {
            if ($routeScope->getId() === $id) {
                return $routeScope;
            }
        }

        return null;
    }
}
