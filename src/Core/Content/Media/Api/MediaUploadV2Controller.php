<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Api;

use Shopware\Core\Content\Media\MediaException;
use Shopware\Core\Content\Media\Upload\MediaUploadParameters;
use Shopware\Core\Content\Media\Upload\MediaUploadService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\ApiRouteScope;
use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @internal
 */
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]])]
#[Package('discovery')]
readonly class MediaUploadV2Controller
{
    public function __construct(private MediaUploadService $mediaUploadService)
    {
    }

    #[Route(path: '/api/_action/media/upload', name: 'api.action.media.upload_v2', methods: ['POST'])]
    public function upload(
        Request $request,
        #[MapRequestPayload]
        MediaUploadParameters $mediaUploadParameters,
        Context $context
    ): JsonResponse {
        return new JsonResponse(['id' => $this->mediaUploadService->uploadFromRequest($request, $context, $mediaUploadParameters)]);
    }

    #[Route(path: '/api/_action/media/upload_by_url', name: 'api.action.media.upload_v2_url', methods: ['POST'])]
    public function uploadUrl(
        Request $request,
        #[MapRequestPayload]
        MediaUploadParameters $mediaUploadParameters,
        Context $context
    ): JsonResponse {
        $url = $request->get('url');

        if (!\is_string($url)) {
            throw MediaException::invalidUrl($url ?? '');
        }

        return new JsonResponse(['id' => $this->mediaUploadService->uploadFromURL($url, $context, $mediaUploadParameters)]);
    }

    #[Route(path: '/api/_action/media/external-link', name: 'api.action.media.external-link', methods: ['POST'])]
    public function externalLink(
        Request $request,
        #[MapRequestPayload]
        MediaUploadParameters $mediaUploadParameters,
        Context $context
    ): JsonResponse {
        $url = $request->get('url');

        if (!\is_string($url)) {
            throw MediaException::invalidUrl($url ?? '');
        }

        return new JsonResponse([
            'id' => $this->mediaUploadService->linkURL($url, $context, $mediaUploadParameters),
        ]);
    }
}
