<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Api;

use Shopware\Core\Framework\ContentSystem\Output\Format\AbstractResponseFactory;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\ApiRouteScope;
use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Previews how a draft content layout renders with real entity data, without persisting the layout.
 *
 * @internal
 */
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]])]
#[Package('framework')]
class ContentPreviewController
{
    public function __construct(
        private readonly ContentPreviewPageBuilder $previewPageBuilder,
        private readonly AbstractResponseFactory $responseFactory,
        private readonly ContentPreviewPayloadStore $payloadStore,
    ) {
    }

    #[Route(path: '/api/_action/content-system/preview/entity', name: 'api.action.content_system.preview.entity', methods: [Request::METHOD_POST])]
    public function preview(
        #[MapRequestPayload(validationFailedStatusCode: Response::HTTP_BAD_REQUEST)]
        ContentPreviewRequest $payload,
        Context $context,
    ): Response {
        return $this->responseFactory->createResponse($this->previewPageBuilder->build($payload, $context)['contentPage']);
    }

    #[Route(path: '/api/_action/content-system/preview/entity/url', name: 'api.action.content_system.preview.entity.url', methods: [Request::METHOD_POST])]
    public function previewUrl(
        #[MapRequestPayload(validationFailedStatusCode: Response::HTTP_BAD_REQUEST)]
        ContentPreviewRequest $payload,
        Request $request,
    ): JsonResponse
    {
        $token = $this->payloadStore->store($this->serializePayload($payload));
        $url = \sprintf(
            '%s%s/content-system/preview/%s',
            $request->getSchemeAndHttpHost(),
            rtrim($request->getBaseUrl(), '/'),
            $token,
        );

        return new JsonResponse([
            'url' => $url,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializePayload(ContentPreviewRequest $payload): array
    {
        return [
            'layout' => $payload->layout,
            'entityType' => $payload->entityType,
            'entityId' => $payload->entityId,
            'salesChannelId' => $payload->salesChannelId,
            'languageId' => $payload->languageId,
            'currencyId' => $payload->currencyId,
            'domainId' => $payload->domainId,
            'customerId' => $payload->customerId,
            'queryParameters' => $payload->queryParameters,
        ];
    }
}
