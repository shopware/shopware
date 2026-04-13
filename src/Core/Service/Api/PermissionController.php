<?php declare(strict_types=1);

namespace Shopware\Core\Service\Api;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\ApiRouteScope;
use Shopware\Core\PlatformRequest;
use Shopware\Core\Service\Permission\PermissionsService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @internal only for use by the service-system
 */
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]])]
#[Package('framework')]
readonly class PermissionController
{
    public function __construct(
        private PermissionsService $permissionsService,
    ) {
    }

    #[Route(
        path: '/api/services/permissions/grant/{revision}',
        name: 'api.services.permissions.grant',
        defaults: [
            'auth_required' => true,
            PlatformRequest::ATTRIBUTE_ACL => ['system.system_config', 'system.plugin_maintain'],
        ],
        methods: [Request::METHOD_POST]
    )]
    public function grantPermissions(string $revision, Context $context): JsonResponse
    {
        $this->permissionsService->grant($revision, $context);

        return new JsonResponse();
    }

    #[Route(
        path: '/api/services/permissions/revoke',
        name: 'api.services.permissions.revoke',
        defaults: [
            'auth_required' => true,
            PlatformRequest::ATTRIBUTE_ACL => ['system.system_config', 'system.plugin_maintain'],
        ],
        methods: [Request::METHOD_POST]
    )]
    public function revokePermissions(Context $context): JsonResponse
    {
        $this->permissionsService->revoke($context);

        return new JsonResponse();
    }
}
