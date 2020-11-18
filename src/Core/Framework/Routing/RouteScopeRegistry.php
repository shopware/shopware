<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Routing;

/**
 * Contains all registered RouteScopes in the system
 */
class RouteScopeRegistry
{
    /**
     * @var AbstractRouteScope[]
     */
    private $routeScopes;

    public function __construct(iterable $routeScopes)
    {
        $this->routeScopes = $routeScopes;
    }

    /**
     * @throws \InvalidArgumentException
     */
    public function getRouteScope(string $id): AbstractRouteScope
    {
        foreach ($this->routeScopes as $routeScope) {
            if ($routeScope->getId() === $id) {
                return $routeScope;
            }
        }

        throw new \InvalidArgumentException('Unknown route scope requested "' . $id . '"');
    }
}
