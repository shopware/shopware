<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Api;

use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\SystemCheck\AppSystemCheck;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\ApiRouteScope;
use Shopware\Core\Framework\Routing\RoutingException;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @experimental stableVersion:v6.8.0
 */
#[Package('framework')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]])]
class AppSystemHealthController
{
    /**
     * @internal
     */
    public function __construct(
        private readonly AppSystemCheck $appSystemCheck,
    ) {
    }

    #[Route(
        path: '/api/app-system/health',
        name: 'api.app_system.health',
        defaults: [PlatformRequest::ATTRIBUTE_ACL => ['system.plugin_maintain']],
        methods: [Request::METHOD_GET],
    )]
    public function health(Request $request): JsonResponse
    {
        $apps = $this->appSystemCheck->collect();

        $appId = $request->query->getString('appId');
        if ($appId !== '') {
            $appId = strtolower($appId);

            if (!Uuid::isValid($appId)) {
                throw RoutingException::invalidRequestParameter('appId');
            }

            if (!isset($apps[$appId])) {
                throw AppException::notFoundByField($appId, 'id');
            }

            $apps = [$appId => $apps[$appId]];
        }

        return new JsonResponse([
            'status' => AppSystemCheck::worstStatus($apps),
            'apps' => (object) $apps,
        ]);
    }
}
