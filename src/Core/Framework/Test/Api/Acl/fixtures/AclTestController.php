<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Api\Acl\fixtures;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\ApiRouteScope;
use Shopware\Core\PlatformRequest;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @internal
 */
#[Package('fundamentals@framework')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]])]
class AclTestController extends AbstractController
{
    #[Route(path: '/api/testroute', name: 'api.test.route', methods: ['GET'], defaults: ['auth_required' => true])]
    public function testRoute(Request $request): JsonResponse
    {
        return new JsonResponse([]);
    }
}
