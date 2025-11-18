<?php declare(strict_types=1);

namespace Shopware\Core\Service\Api;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Service\Permission\PermissionsService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @internal only for use by the service-system
 */
#[Route(defaults: ['_routeScope' => ['api']])]
#[Package('framework')]
readonly class PermissionController
{
    public function __construct(
        private PermissionsService $permissionsService,
    ) {
    }

    #[Route(path: '/api/services/permissions/grant/{revision}', name: 'api.services.permissions.grant', defaults: ['auth_required' => true, '_acl' => ['system.system_config', 'system.plugin_maintain']], methods: ['POST'])]
    public function grantPermissions(string $revision, Context $context): JsonResponse
    {
        $this->permissionsService->grant($revision, $context);

        return new JsonResponse();
    }

    #[Route(path: '/api/services/permissions/revoke', name: 'api.services.permissions.revoke', defaults: ['auth_required' => true, '_acl' => ['system.system_config', 'system.plugin_maintain']], methods: ['POST'])]
    public function revokePermissions(Context $context): JsonResponse
    {
        $this->permissionsService->revoke($context);

        return new JsonResponse();
    }
}
