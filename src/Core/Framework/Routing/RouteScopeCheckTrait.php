<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Routing;

use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Symfony\Component\HttpFoundation\Request;

trait RouteScopeCheckTrait
{
    private function isRequestScoped(Request $request, string ...$scopeNames): bool
    {
        /** @var RouteScope|null $routeScope */
        $routeScope = $request->attributes->get('_routeScope');

        if (!$routeScope) {
            return false;
        }

        foreach ($scopeNames as $scopeName) {
            if ($routeScope->hasScope($scopeName)) {
                return true;
            }
        }

        return false;
    }
}
