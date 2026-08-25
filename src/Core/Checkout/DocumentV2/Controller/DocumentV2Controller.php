<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2\Controller;

use Shopware\Core\Checkout\Document\DocumentCollection;
use Shopware\Core\Checkout\Document\DocumentEntity;
use Shopware\Core\Checkout\DocumentV2\DocumentV2Exception;
use Shopware\Core\Checkout\DocumentV2\Generation\DocumentArchiveGenerator;
use Shopware\Core\Checkout\DocumentV2\Generation\DocumentGenerationRequest;
use Shopware\Core\Checkout\DocumentV2\Generation\DocumentGenerationRequestResolver;
use Shopware\Core\Checkout\DocumentV2\Generation\DocumentGenerator;
use Shopware\Core\Checkout\DocumentV2\Generation\DocumentPersister;
use Shopware\Core\Checkout\DocumentV2\Renderer\DocumentRendererRegistry;
use Shopware\Core\Checkout\DocumentV2\Service\DocumentFileResolver;
use Shopware\Core\Checkout\DocumentV2\Type\DocumentTypeRegistry;
use Shopware\Core\Content\Media\Exception\IllegalFileNameException;
use Shopware\Core\Content\Media\File\FileNameProvider;
use Shopware\Core\Content\Media\MediaService;
use Shopware\Core\Content\Media\Util\PathHelper;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\ApiRouteScope;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\InputBag;
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
    private const DOCUMENT_FILE_ASSOCIATIONS = [
        'documentFiles.media',
        'documentMediaFile',
        'documentA11yMediaFile',
        'documentType',
    ];

    /**
     * @internal
     *
     * @param EntityRepository<DocumentCollection> $documentRepository
     */
    public function __construct(
        private readonly DocumentGenerator $documentGenerator,
        private readonly DocumentRendererRegistry $documentRendererRegistry,
        private readonly DocumentTypeRegistry $documentTypeRegistry,
        private readonly DocumentArchiveGenerator $documentArchiveGenerator,
        private readonly EntityRepository $documentRepository,
        private readonly DocumentPersister $documentPersister,
        private readonly MediaService $mediaService,
        private readonly FileNameProvider $fileNameProvider,
        private readonly DocumentFileResolver $documentFileResolver,
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
        $documentTypes = [];

        foreach ($this->documentTypeRegistry->getTechnicalNames() as $documentType) {
            $documentTypes[$documentType] = [
                'formats' => $this->documentTypeRegistry->getSupportedFormats($documentType),
            ];
        }

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
        $document = $this->documentGenerator->generate(
            $generationRequest,
            $context,
        );

        return new JsonResponse([
            'deepLinkCode' => $document->getDeepLinkCode(),
            'documentId' => $document->getId(),
            'formats' => $generationRequest->requestedFormats,
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

    #[Route(
        path: '/api/_action/order/document-v2/upload',
        name: 'api.action.order.document-v2.upload',
        defaults: [PlatformRequest::ATTRIBUTE_ACL => ['document:create']],
        methods: [Request::METHOD_POST],
    )]
    public function upload(Request $request, Context $context): JsonResponse
    {
        $payload = $request->getContentTypeFormat() === 'json' ? $request->getPayload() : $request->query;

        $documentType = $this->requirePayloadString($payload, 'documentType');
        $format = $this->requirePayloadString($payload, 'format');

        $this->documentTypeRegistry->validateFormats($documentType, [$format]);

        $mediaId = $payload->getString('mediaId');
        $documentNumber = $payload->getString('documentNumber');

        if ($mediaId === '') {
            $mediaId = $context->scope(
                Context::SYSTEM_SCOPE,
                function (Context $scopedContext) use ($request, $payload): string {
                    $mediaFile = $this->mediaService->fetchFile($request);

                    $fileName = $this->fileNameProvider->provide(
                        $this->resolveUploadedFileName($payload),
                        $mediaFile->getFileExtension(),
                        null,
                        $scopedContext,
                    );

                    return $this->mediaService->saveMediaFile(
                        $mediaFile,
                        $fileName,
                        $scopedContext,
                        DocumentPersister::MEDIA_FOLDER,
                    );
                },
            );
        }

        $document = $this->documentPersister->persistUploaded(
            $documentType,
            $this->requirePayloadString($payload, 'orderId'),
            $this->requirePayloadString($payload, 'orderVersionId'),
            $documentNumber,
            $format,
            $mediaId,
            $payload->getString('referencedDocumentId') ?: null,
            $context,
        );

        return new JsonResponse([
            'documentId' => $document->getId(),
            'deepLinkCode' => $document->getDeepLinkCode(),
            'formats' => [$format],
        ]);
    }

    #[Route(
        path: '/api/_action/order/document-v2/{documentId}/download/{format}',
        name: 'api.action.order.document-v2.download',
        defaults: [PlatformRequest::ATTRIBUTE_ACL => ['document:read']],
        methods: [Request::METHOD_GET],
    )]
    public function download(
        string $documentId,
        string $format,
        Context $context,
    ): Response {
        $document = $this->loadDocument($documentId, $context);

        if (!$document instanceof DocumentEntity) {
            throw DocumentV2Exception::documentNotFound($documentId);
        }

        $resolvedFile = $this->documentFileResolver->resolve($document, $format);
        if ($resolvedFile === null) {
            throw DocumentV2Exception::documentFormatUnavailable($documentId, $format);
        }

        $fileExtension = $resolvedFile->fileExtension;
        if ($fileExtension === '') {
            $fileExtension = $this->documentRendererRegistry->getFileExtension($format);
            if ($fileExtension === null) {
                throw DocumentV2Exception::documentFileExtensionUnavailable($documentId, $format);
            }
        }

        $content = $context->scope(
            Context::SYSTEM_SCOPE,
            fn (Context $scopedContext): string => $this->mediaService->loadFile($resolvedFile->media->getId(), $scopedContext),
        );

        $fileName = $resolvedFile->fileName . '.' . $fileExtension;

        return $this->createResponse(
            $fileName,
            $content,
            $resolvedFile->mimeType,
            HeaderUtils::DISPOSITION_ATTACHMENT,
        );
    }

    #[Route(
        path: '/api/_action/order/document-v2/download-archive',
        name: 'api.action.order.document-v2.download.archive',
        defaults: [PlatformRequest::ATTRIBUTE_ACL => ['document:read']],
        methods: [Request::METHOD_POST],
    )]
    public function downloadArchive(Request $request, Context $context): Response
    {
        $documentIds = $request->getPayload()->all()['documentIds'] ?? null;

        if (!\is_array($documentIds) || $documentIds === []) {
            throw DocumentV2Exception::invalidRequestParameter('documentIds');
        }

        $documentIds = array_values(array_unique(array_filter($documentIds, \is_string(...))));

        if ($documentIds === []) {
            throw DocumentV2Exception::invalidRequestParameter('documentIds');
        }

        if (\count($documentIds) > DocumentArchiveGenerator::MAX_DOCUMENTS) {
            throw DocumentV2Exception::documentArchiveLimitExceeded(
                \count($documentIds),
                DocumentArchiveGenerator::MAX_DOCUMENTS,
            );
        }

        $documents = $this->loadDocuments($documentIds, $context);

        if ($documents->count() === 0) {
            throw DocumentV2Exception::documentArchiveUnavailable($documentIds);
        }

        $archive = $this->documentArchiveGenerator->archive($documents, $context);

        if ($archive === null) {
            throw DocumentV2Exception::documentArchiveUnavailable($documentIds);
        }

        return $this->createResponse(
            $archive->getName(),
            $archive->getContent(),
            $archive->getContentType(),
            HeaderUtils::DISPOSITION_ATTACHMENT,
        );
    }

    private function loadDocument(string $documentId, Context $context): ?DocumentEntity
    {
        $criteria = (new Criteria([$documentId]))
            ->addAssociations(self::DOCUMENT_FILE_ASSOCIATIONS);

        $document = $this->documentRepository->search($criteria, $context)->getEntities()->first();

        return $document instanceof DocumentEntity ? $document : null;
    }

    /**
     * @param list<string> $documentIds
     */
    private function loadDocuments(array $documentIds, Context $context): DocumentCollection
    {
        $criteria = (new Criteria($documentIds))
            ->addAssociations(self::DOCUMENT_FILE_ASSOCIATIONS)
            ->addAssociation('order');

        return $this->documentRepository->search($criteria, $context)->getEntities();
    }

    /**
     * @param InputBag<string|int|float|bool|null> $payload
     */
    private function requirePayloadString(InputBag $payload, string $key): string
    {
        $value = $payload->getString($key);

        if ($value === '') {
            throw DocumentV2Exception::invalidRequestParameter($key);
        }

        return $value;
    }

    /**
     * @param InputBag<string|int|float|bool|null> $payload
     */
    private function resolveUploadedFileName(InputBag $payload): string
    {
        $fileName = $payload->getString('fileName') ?: $payload->getString('documentNumber');

        return $fileName !== '' ? $fileName : Uuid::randomHex();
    }

    private function createResponse(
        string $filename,
        string $content,
        string $contentType,
        string $dispositionType = HeaderUtils::DISPOSITION_INLINE,
    ): Response {
        $response = new Response($content);

        try {
            $filenameFallback = PathHelper::stripNonAsciiAndControlChars($filename, '_');
        } catch (IllegalFileNameException) {
            $filenameFallback = '';
        }

        $disposition = HeaderUtils::makeDisposition(
            $dispositionType,
            $filename,
            $filenameFallback
        );

        $response->headers->set('Content-Type', $contentType);
        $response->headers->set('Content-Disposition', $disposition);

        return $response;
    }
}
