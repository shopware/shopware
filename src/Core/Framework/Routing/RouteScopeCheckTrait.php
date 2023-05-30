<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Routing;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\Request;

#[Package('core')]
trait RouteScopeCheckTrait
{
    abstract protected function getScopeRegistry(): RouteScopeRegistry;

    private function isRequestScoped(Request $request, string $scopeClass): bool
    {
        /** @var list<string> $scopes */
        $scopes = $request->attributes->get(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE, []);

        if ($scopes === []) {
            return false;
        }

        foreach ($scopes as $scopeId) {
            $scope = $this->getScopeRegistry()->getRouteScope($scopeId);

            if ($scope instanceof $scopeClass) {
                return true;
            }
        }

        return false;
    }
}
