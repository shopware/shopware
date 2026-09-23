<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Api;

use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Media\MediaException;
use Shopware\Core\Content\Media\Thumbnail\ExternalThumbnailsParameters;
use Shopware\Core\Content\Media\Upload\MediaUploadParameters;
use Shopware\Core\Content\Media\Upload\MediaUploadService;
use Shopware\Core\Framework\Adapter\Request\RequestParamHelper;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\ApiRouteScope;
use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @internal
 */
#[Package('discovery')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]])]
readonly class MediaUploadV2Controller
{
    /**
     * @param EntityRepository<MediaCollection> $mediaRepository
     */
    public function __construct(
        private MediaUploadService $mediaUploadService,
        private EntityRepository $mediaRepository,
    ) {
    }

    #[Route(path: '/api/_action/media/upload', name: 'api.action.media.upload_v2', defaults: [PlatformRequest::ATTRIBUTE_ACL => ['media:create']], methods: ['POST'])]
    public function upload(
        Request $request,
        #[MapRequestPayload]
        MediaUploadParameters $mediaUploadParameters,
        Context $context
    ): JsonResponse {
        return new JsonResponse(['id' => $this->mediaUploadService->uploadFromRequest($request, $context, $mediaUploadParameters)]);
    }

    #[Route(path: '/api/_action/media/upload_by_url', name: 'api.action.media.upload_v2_url', defaults: [PlatformRequest::ATTRIBUTE_ACL => ['media:create']], methods: ['POST'])]
    public function uploadUrl(
        Request $request,
        #[MapRequestPayload]
        MediaUploadParameters $mediaUploadParameters,
        Context $context
    ): JsonResponse {
        $url = RequestParamHelper::get($request, 'url');

        if (!\is_string($url)) {
            throw MediaException::invalidUrl(print_r($url, true));
        }

        return new JsonResponse(['id' => $this->mediaUploadService->uploadFromURL($url, $context, $mediaUploadParameters)]);
    }

    #[Route(path: '/api/_action/media/external-link', name: 'api.action.media.external-link', defaults: [PlatformRequest::ATTRIBUTE_ACL => ['media:create']], methods: ['POST'])]
    public function externalLink(
        Request $request,
        #[MapRequestPayload]
        MediaUploadParameters $mediaUploadParameters,
        Context $context
    ): JsonResponse {
        $url = RequestParamHelper::get($request, 'url');

        if (!\is_string($url)) {
            throw MediaException::invalidUrl(print_r($url, true));
        }

        return new JsonResponse([
            'id' => $this->mediaUploadService->linkURL($url, $context, $mediaUploadParameters),
        ]);
    }

    #[Route(path: '/api/_action/media/{mediaId}/external-thumbnails', name: 'api.action.media.add-external-thumbnails', defaults: [PlatformRequest::ATTRIBUTE_ACL => ['media_thumbnail:create']], methods: [Request::METHOD_POST])]
    public function addExternalThumbnails(
        string $mediaId,
        #[MapRequestPayload]
        ExternalThumbnailsParameters $params,
        Context $context
    ): JsonResponse {
        $media = $this->validateAndGetExternalMedia($mediaId, $context);

        $this->mediaUploadService->addExternalThumbnailsToMedia($media->getId(), $params->thumbnails, $context);

        return new JsonResponse([
            'mediaId' => $media->getId(),
            'thumbnailsCreated' => $params->thumbnails->count(),
        ], Response::HTTP_CREATED);
    }

    #[Route(path: '/api/_action/media/{mediaId}/external-thumbnails', name: 'api.action.media.delete-external-thumbnails', defaults: [PlatformRequest::ATTRIBUTE_ACL => ['media_thumbnail:delete']], methods: [Request::METHOD_DELETE])]
    public function deleteExternalThumbnails(
        string $mediaId,
        Context $context
    ): JsonResponse {
        $media = $this->validateAndGetExternalMedia($mediaId, $context);

        $this->mediaUploadService->deleteAllExternalThumbnails($media->getId(), $context);

        return new JsonResponse([
            'mediaId' => $media->getId(),
        ]);
    }

    private function validateAndGetExternalMedia(string $id, Context $context): MediaEntity
    {
        $criteria = new Criteria([$id]);
        $criteria->addAssociation('thumbnails');

        $media = $this->mediaRepository->search($criteria, $context)->getEntities()->first();

        if ($media === null) {
            throw MediaException::mediaNotFound($id);
        }

        if (!$media->hasPath()) {
            throw MediaException::emptyMediaPath($media->getId());
        }

        if (!MediaUploadService::isExternalUrl($media->getPath())) {
            throw MediaException::externalMediaRequired($id);
        }

        return $media;
    }
}
