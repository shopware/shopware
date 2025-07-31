<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\RouteScope;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Routing\ApiRouteScope;
use Shopware\Core\PlatformRequest;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @internal
 */
class ControllerWithoutRouteAttribute extends AbstractController
{
    #[Route(path: '/api/_action/1', name: 'api.action.media-folder.dissolve', methods: ['POST'])]
    public function withoutScope(string $folderId, Context $context): Response
    {
        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    #[Route(
        path: '/api/_action/2',
        name: 'api.action.media-folder.dissolve2',
        defaults: ['_routeScope' => ['api']],
        methods: ['POST']
    )]
    public function withScope(string $folderId, Context $context): Response
    {
        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    #[Route(
        path: '/api/_action/3',
        name: 'api.action.media-folder.dissolve3',
        defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]],
        methods: ['POST']
    )]
    public function withScopeConst(string $folderId, Context $context): Response
    {
        return new Response(null, Response::HTTP_NO_CONTENT);
    }
}
