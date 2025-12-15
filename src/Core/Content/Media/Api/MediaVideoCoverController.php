<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Api;

use Shopware\Core\Content\Media\MediaException;
use Shopware\Core\Content\Media\Service\VideoCoverService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: ['_routeScope' => ['api']])]
#[Package('discovery')]
class MediaVideoCoverController extends AbstractController
{
    /**
     * @internal
     */
    public function __construct(private readonly VideoCoverService $videoCoverService)
    {
    }

    #[Route(path: '/api/_action/media/{mediaId}/video-cover', name: 'api.action.media.set_video_cover', methods: ['POST'], defaults: ['_acl' => ['media.editor']])]
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
