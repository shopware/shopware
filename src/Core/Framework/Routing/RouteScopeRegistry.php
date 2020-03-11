<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Routing;

/**
 * Contains all registered RouteScopes in the system
 */
class RouteScopeRegistry
{
    /**
     * @var RouteScopeInterface[]|AbstractRouteScope[]
     */
    private $routeScopes;

    public function __construct(iterable $routeScopes)
    {
        $this->routeScopes = $routeScopes;
    }

    /**
     * @throws \InvalidArgumentException
     *
     * @return AbstractRouteScope|RouteScopeInterface
     */
    public function getRouteScope(string $id)
    {
        foreach ($this->routeScopes as $routeScope) {
            if ($routeScope->getId() === $id) {
                return $routeScope;
            }
        }

        throw new \InvalidArgumentException('Unknown route scope requested "' . $id . '"');
    }
}
