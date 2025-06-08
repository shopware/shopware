<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Api\EventListener\FixturesPhp;

use Shopware\Core\Framework\Routing\StoreApiRouteScope;
use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @internal
 */
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]])]
class SalesChannelAuthenticationListenerTestRoute
{
    #[
        Route(
            path: '/store-api/test/sales-channel-authentication-listener/default',
            name: 'store-api.test.authentication_listener.default',
            methods: [Request::METHOD_GET]
        )
    ]
    public function defaultAction(): JsonResponse
    {
        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[
        Route(
            path: '/store-api/test/sales-channel-authentication-listener/maintenance-allowed',
            name: 'store-api.test.authentication_listener.maintenance_allowed',
            defaults: [PlatformRequest::ATTRIBUTE_IS_ALLOWED_IN_MAINTENANCE => true],
            methods: [Request::METHOD_GET]
        )
    ]
    public function maintenanceAllowed(): JsonResponse
    {
        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[
        Route(
            path: '/store-api/test/sales-channel-authentication-listener/maintenance-disallowed',
            name: 'store-api.test.authentication_listener.maintenance_disallowed',
            defaults: [PlatformRequest::ATTRIBUTE_IS_ALLOWED_IN_MAINTENANCE => false],
            methods: [Request::METHOD_GET]
        )
    ]
    public function maintenanceDisallowed(): JsonResponse
    {
        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[
        Route(
            path: '/store-api/test/sales-channel-authentication-listener/no-auth-required',
            name: 'store-api.test.authentication_listener.no_auth_required',
            defaults: ['auth_required' => false],
            methods: [Request::METHOD_GET]
        )
    ]
    public function noAuthRequiredAction(): JsonResponse
    {
        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
