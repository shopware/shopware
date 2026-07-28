<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Routing\Telemetry;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\ApiRouteScope;
use Shopware\Core\Framework\Routing\StoreApiRouteScope;
use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\Request;

/**
 * Resolves the coarse application `area` of a main HTTP request.
 *
 * Two routes are special-cased by name because their load/performance profile requires separate
 * instrumentation: `api.action.sync` (admin route scope) → sync-api, and `payment.finalize.transaction` (no scope) → payment.
 *
 * Everything else is classified by route scope.
 *
 * Bounded output set (closed match, `other` as default), so the consuming metric labels may use `policy: open`.
 *
 * Known outputs: storefront, store-api, admin-api, administration, sync-api, payment, other.
 *
 * @internal
 *
 * @final
 *
 * @experimental feature:TELEMETRY_METRICS stableVersion:v6.8.0
 */
#[Package('framework')]
class AreaResolver
{
    public function resolve(Request $request): string
    {
        $route = (string) $request->attributes->get('_route', '');

        // sync should map to separate area as it's response duration will be an outlier in the most cases
        if ($route === 'api.action.sync') {
            return 'sync-api';
        }

        // payment finalization route has no scope attribute
        if ($route === 'payment.finalize.transaction') {
            return 'payment';
        }

        /** @var list<string> $scopes */
        $scopes = $request->attributes->get(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE, []);

        return match (true) {
            \in_array(StoreApiRouteScope::ID, $scopes, true) => 'store-api',
            \in_array(ApiRouteScope::ID, $scopes, true) => 'admin-api',
            // Storefront/Administration route scope classes are not always present, using string literals
            \in_array('storefront', $scopes, true) => 'storefront',
            \in_array('administration', $scopes, true) => 'administration',
            default => 'other',
        };
    }
}
