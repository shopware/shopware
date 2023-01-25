<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Routing;

use Shopware\Core\Framework\Log\Package;

#[Package('core
Contains all registered RouteScopes in the system')]
class RouteScopeRegistry
{
    /**
     * @internal
     *
     * @param AbstractRouteScope[] $routeScopes
     */
    public function __construct(private readonly iterable $routeScopes)
    {
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
