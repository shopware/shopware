<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\RouteScope;

use Shopware\Core\Framework\Context;
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

    #[Route(path: '/api/_action/2', name: 'api.action.media-folder.dissolve2', methods: ['POST'], defaults: ['_routeScope' => ['api']])]
    public function withScope(string $folderId, Context $context): Response
    {
        return new Response(null, Response::HTTP_NO_CONTENT);
    }
}
