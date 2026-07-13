<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2\Controller;

use Shopware\Core\Checkout\DocumentV2\DocumentV2Exception;
use Shopware\Core\Checkout\DocumentV2\Generation\DocumentGenerationRequest;
use Shopware\Core\Checkout\DocumentV2\Generation\DocumentGenerationRequestResolver;
use Shopware\Core\Checkout\DocumentV2\Generation\DocumentGenerator;
use Shopware\Core\Checkout\DocumentV2\Renderer\DocumentRendererRegistry;
use Shopware\Core\Content\Media\Exception\IllegalFileNameException;
use Shopware\Core\Content\Media\Util\PathHelper;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\ApiRouteScope;
use Shopware\Core\PlatformRequest;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @internal
 */
#[Package('after-sales')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]])]
final class DocumentV2Controller extends AbstractController
{
    /**
     * @internal
     */
    public function __construct(
        private readonly DocumentGenerator $documentGenerator,
        private readonly DocumentRendererRegistry $documentRendererRegistry,
    ) {
    }

    #[Route(
        path: '/api/_action/order/document-v2/available-types',
        name: 'api.action.order.document-v2.available-types',
        defaults: [PlatformRequest::ATTRIBUTE_ACL => ['document:read']],
        methods: [Request::METHOD_GET],
    )]
    public function availableTypes(): JsonResponse
    {
        $documentTypes = array_map(function (array $formats) {
            return [
                'formats' => $formats,
            ];
        }, $this->documentRendererRegistry->getSupportedFormatsByDocumentType());

        return new JsonResponse([
            'documentTypes' => $documentTypes,
        ]);
    }

    #[Route(
        path: '/api/_action/order/document-v2/create',
        name: 'api.action.order.document-v2.create',
        defaults: [PlatformRequest::ATTRIBUTE_ACL => ['document:create']],
        methods: [Request::METHOD_POST],
    )]
    public function create(
        #[MapRequestPayload(resolver: DocumentGenerationRequestResolver::class)]
        DocumentGenerationRequest $generationRequest,
        Context $context,
    ): JsonResponse {
        $this->assertSupported($generationRequest->documentType, $generationRequest->requestedFormats);

        $document = $this->documentGenerator->generate(
            $generationRequest,
            $context,
        );

        return new JsonResponse([
            'documentDeepLink' => $document->getDeepLinkCode(),
            'documentId' => $document->getId(),
            'fileTypes' => $generationRequest->requestedFormats,
        ]);
    }

    #[Route(
        path: '/api/_action/order/document-v2/preview',
        name: 'api.action.order.document-v2.preview',
        defaults: [PlatformRequest::ATTRIBUTE_ACL => ['document:read']],
        methods: [Request::METHOD_POST],
    )]
    public function preview(
        #[MapRequestPayload(resolver: DocumentGenerationRequestResolver::class)]
        DocumentGenerationRequest $generationRequest,
        Context $context,
    ): Response {
        $this->assertSupported($generationRequest->documentType, $generationRequest->requestedFormats);

        $preview = $this->documentGenerator->preview(
            $generationRequest,
            $context,
        );

        return $this->createResponse(
            $preview->getName(),
            $preview->getContent(),
            $preview->getContentType(),
        );
    }

    /**
     * @param list<string> $fileTypes
     */
    private function assertSupported(string $documentType, array $fileTypes): void
    {
        $supportedFormats = array_keys($this->documentRendererRegistry->mapRenderersByFormat($documentType));

        foreach ($fileTypes as $fileType) {
            if (!\in_array($fileType, $supportedFormats, true)) {
                throw DocumentV2Exception::rendererNotFound($fileType, $documentType);
            }
        }
    }

    private function createResponse(string $filename, string $content, string $contentType): Response
    {
        $response = new Response($content);

        try {
            $filenameFallback = PathHelper::stripNonAsciiAndControlChars($filename, '_');
        } catch (IllegalFileNameException) {
            $filenameFallback = '';
        }

        $disposition = HeaderUtils::makeDisposition(
            HeaderUtils::DISPOSITION_INLINE,
            $filename,
            $filenameFallback
        );

        $response->headers->set('Content-Type', $contentType);
        $response->headers->set('Content-Disposition', $disposition);

        return $response;
    }
}
