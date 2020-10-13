<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Api\Acl\fixtures;

use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"api"})
 */
class AclTestController extends AbstractController
{
    /**
     * @Route("/api/testroute", name="api.test.route", methods={"GET"}, defaults={"auth_required"=true})
     */
    public function testRoute(Request $request): JsonResponse
    {
        return new JsonResponse([]);
    }
}
