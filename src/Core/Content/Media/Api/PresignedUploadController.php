<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Api;

use Shopware\Core\Content\Media\Upload\PresignedMediaUploadService;
use Shopware\Core\Content\Media\Upload\PresignedUploadFinalizePayload;
use Shopware\Core\Content\Media\Upload\PresignedUploadPreparePayload;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\ApiRouteScope;
use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @internal
 */
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]])]
#[Package('discovery')]
readonly class PresignedUploadController
{
    public function __construct(private PresignedMediaUploadService $presignedMediaUploadService)
    {
    }

    #[Route(path: '/api/_action/media/presign-upload', name: 'api.action.media.presign-upload', methods: ['POST'])]
    public function prepare(
        #[MapRequestPayload]
        PresignedUploadPreparePayload $payload,
        Context $context,
    ): JsonResponse {
        return new JsonResponse(
            $this->presignedMediaUploadService->prepare($payload, $context)
        );
    }

    #[Route(path: '/api/_action/media/{mediaId}/finalize-upload', name: 'api.action.media.finalize-upload', methods: ['POST'])]
    public function finalize(
        string $mediaId,
        #[MapRequestPayload]
        PresignedUploadFinalizePayload $payload,
        Context $context,
    ): JsonResponse {
        $this->presignedMediaUploadService->finalize($mediaId, $payload, $context);

        return new JsonResponse(['mediaId' => $mediaId]);
    }
}
