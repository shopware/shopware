<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Routing;

use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Symfony\Component\HttpFoundation\Request;

trait RouteScopeCheckTrait
{
    abstract protected function getScopeRegistry(): RouteScopeRegistry;

    private function isRequestScoped(Request $request, string $scopeClass): bool
    {
        /** @var RouteScope|null $routeScope */
        $routeScope = $request->attributes->get('_routeScope');

        if (!$routeScope) {
            return false;
        }

        foreach ($routeScope->getScopes() as $scopeId) {
            $scope = $this->getScopeRegistry()->getRouteScope($scopeId);

            if ($scope instanceof $scopeClass) {
                return true;
            }
        }

        return false;
    }
}
