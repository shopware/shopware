<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Api;

use Shopware\Core\Content\Media\MediaException;
use Shopware\Core\Content\Media\Service\VideoCoverService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\ApiRouteScope;
use Shopware\Core\PlatformRequest;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]])]
#[Package('discovery')]
class MediaVideoCoverController extends AbstractController
{
    /**
     * @internal
     */
    public function __construct(private readonly VideoCoverService $videoCoverService)
    {
    }

    #[Route(
        path: '/api/_action/media/{mediaId}/video-cover',
        name: 'api.action.media.set_video_cover',
        defaults: [PlatformRequest::ATTRIBUTE_ACL => ['media.editor']],
        methods: [Request::METHOD_POST]
    )]
    public function assignVideoCover(string $mediaId, Request $request, Context $context): JsonResponse
    {
        try {
            $coverMediaId = $request->request->get('coverMediaId');
        } catch (BadRequestException) {
            throw MediaException::invalidRequestParameter('coverMediaId');
        }

        if ($coverMediaId !== null && !\is_string($coverMediaId)) {
            throw MediaException::invalidRequestParameter('coverMediaId');
        }

        $this->videoCoverService->assignCoverToVideo($mediaId, $coverMediaId, $context);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
