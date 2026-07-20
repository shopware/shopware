<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\RouteScope;

use Shopware\Core\Framework\Context;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @internal
 * Intentionally not using the constants for the route scope
 */
#[Route(defaults: ['_routeScope' => ['api']])]
class ControllerWithRouteAttribute extends AbstractController
{
    #[Route(
        path: '/api/_action/1',
        name: 'api.action.media-folder.dissolve',
        methods: [Request::METHOD_POST]
    )]
    public function inheritScope(string $folderId, Context $context): Response
    {
        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    #[Route(
        path: '/api/_action/2',
        name: 'api.action.media-folder.dissolve',
        defaults: ['_routeScope' => []],
        methods: [Request::METHOD_POST]
    )]
    public function resetScope(string $folderId, Context $context): Response
    {
        return new Response(null, Response::HTTP_NO_CONTENT);
    }
}
