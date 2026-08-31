<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2\Generation;

use Shopware\Core\Checkout\Document\Aggregate\DocumentType\DocumentTypeCollection;
use Shopware\Core\Checkout\Document\DocumentCollection;
use Shopware\Core\Checkout\Document\DocumentEntity;
use Shopware\Core\Checkout\DocumentV2\Aggregate\DocumentFile\DocumentFileCollection;
use Shopware\Core\Checkout\DocumentV2\DocumentFormat;
use Shopware\Core\Checkout\DocumentV2\DocumentType;
use Shopware\Core\Checkout\DocumentV2\DocumentV2Exception;
use Shopware\Core\Checkout\DocumentV2\Event\DocumentGeneratedEvent;
use Shopware\Core\Checkout\DocumentV2\Provider\DocumentMetaProvider;
use Shopware\Core\Checkout\DocumentV2\Provider\RenderData\DocumentMetaRenderData;
use Shopware\Core\Checkout\DocumentV2\Struct\ReferencedDocument;
use Shopware\Core\Checkout\DocumentV2\Struct\RenderInput;
use Shopware\Core\Checkout\DocumentV2\Struct\RenderState;
use Shopware\Core\Checkout\DocumentV2\Type\DocumentTypeRegistry;
use Shopware\Core\Content\Media\File\FileNameProvider;
use Shopware\Core\Content\Media\MediaService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Random;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Persists a document and one document_file per requested format, either freshly generated
 * ({@see self::persist()}) or uploaded by a user ({@see self::persistUploaded()}).
 *
 * One document row represents the shared document number and order snapshot, while each
 * requested output format is stored as a separate document_file linked to the same document.
 *
 * Media is written under {@see Context::SYSTEM_SCOPE}.
 *
 * @internal
 */
#[Package('after-sales')]
final readonly class DocumentPersister
{
    final public const MEDIA_FOLDER = 'document';

    /**
     * @param EntityRepository<DocumentCollection> $documentRepository
     * @param EntityRepository<DocumentFileCollection> $documentFileRepository
     * @param EntityRepository<DocumentTypeCollection> $documentTypeRepository
     */
    public function __construct(
        private EntityRepository $documentRepository,
        private EntityRepository $documentFileRepository,
        private EntityRepository $documentTypeRepository,
        private MediaService $mediaService,
        private DocumentTypeRegistry $documentTypeRegistry,
        private FileNameProvider $fileNameProvider,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    /**
     * @param list<string> $requestedFormats
     *
     * @throws DocumentV2Exception
     */
    public function persist(
        DocumentGenerationRequest $generationRequest,
        RenderInput $input,
        RenderState $state,
        array $requestedFormats,
        ?ReferencedDocument $resolvedReference,
        Context $context,
    ): DocumentEntity {
        $documentId = Uuid::randomHex();

        // replaced by a unique index on document(document_number, type_name) once document_type_id is dropped.
        $this->assertDocumentNumberIsUnique($generationRequest, $input->documentNumber, $context);

        $persistedFiles = $this->writeMediaFiles(
            $state,
            $requestedFormats,
            $context,
        );

        $meta = $input->requireData(DocumentMetaProvider::KEY, DocumentMetaRenderData::class);
        $documentFiles = [];

        foreach ($persistedFiles as $format => $mediaId) {
            $documentFiles[] = [
                'id' => Uuid::randomHex(),
                'documentId' => $documentId,
                'documentFormat' => $format,
                'mediaId' => $mediaId,
            ];
        }

        return $this->writeDocument(
            [
                'id' => $documentId,
                'orderId' => $generationRequest->orderId,
                'orderVersionId' => $input->order->getVersionId(),
                'documentTypeId' => $this->getDocumentTypeId($generationRequest->documentType, $context), // remove with v6.9.0
                'typeName' => $generationRequest->documentType,
                'documentMediaFileId' => $persistedFiles[DocumentFormat::PDF->value] ?? (array_values($persistedFiles)[0] ?? null),
                'documentA11yMediaFileId' => $persistedFiles[DocumentFormat::HTML->value] ?? null,
                'referencedDocumentId' => $resolvedReference?->id,
                'deepLinkCode' => Random::getAlphanumericString(32),
                'config' => [
                    'documentNumber' => $input->documentNumber,
                    'displayInCustomerAccount' => (bool) ($meta->legacyConfig['displayInCustomerAccount'] ?? false),
                ],
            ],
            $documentFiles,
            $generationRequest->documentType,
            $input->documentNumber,
            $context,
        );
    }

    /**
     * Persists a user-uploaded document, bypassing rendering. The uploaded file is used as-is for the single
     * requested format.
     *
     * @throws DocumentV2Exception
     */
    public function persistUploaded(
        string $documentType,
        string $orderId,
        string $orderVersionId,
        string $documentNumber,
        string $format,
        string $mediaId,
        ?string $referencedDocumentId,
        Context $context,
    ): DocumentEntity {
        $documentId = Uuid::randomHex();

        return $this->writeDocument(
            [
                'id' => $documentId,
                'orderId' => $orderId,
                'orderVersionId' => $orderVersionId,
                'documentTypeId' => $this->getDocumentTypeId($documentType, $context),
                'documentMediaFileId' => $mediaId,
                'referencedDocumentId' => $referencedDocumentId,
                'static' => true,
                'deepLinkCode' => Random::getAlphanumericString(32),
                // Omit an empty document number so the generated document_number column stays NULL
                'config' => $documentNumber === '' ? [] : ['documentNumber' => $documentNumber],
            ],
            [
                [
                    'id' => Uuid::randomHex(),
                    'documentId' => $documentId,
                    'documentFormat' => $format,
                    'mediaId' => $mediaId,
                ],
            ],
            $documentType,
            $documentNumber,
            $context,
        );
    }

    /**
     * @param array<string, mixed> $documentData
     * @param list<array<string, mixed>> $documentFiles
     *
     * @throws DocumentV2Exception
     */
    private function writeDocument(
        array $documentData,
        array $documentFiles,
        string $documentType,
        string $documentNumber,
        Context $context
    ): DocumentEntity {
        $this->documentRepository->create([$documentData], $context);
        $this->documentFileRepository->create($documentFiles, $context);

        $document = $this->documentRepository->search(
            (new Criteria([$documentData['id']]))->addAssociation('documentFiles.media'),
            $context,
        )->getEntities()->first();

        if (!$document instanceof DocumentEntity) {
            throw DocumentV2Exception::documentNotPersisted($documentNumber);
        }

        $this->eventDispatcher->dispatch(new DocumentGeneratedEvent(
            $document->getId(),
            $document->getOrderId(),
            $document->getOrderVersionId(),
            $documentType,
            $documentNumber,
            $context,
        ));

        return $document;
    }

    /**
     * @param list<string> $requestedFormats
     *
     * @return array<string, string> map<format, mediaId>
     */
    private function writeMediaFiles(RenderState $state, array $requestedFormats, Context $context): array
    {
        $persisted = [];

        foreach ($requestedFormats as $format) {
            $result = $state->require($format);

            $persisted[$format] = $context->scope(
                Context::SYSTEM_SCOPE,
                function (Context $scoped) use ($result): string {
                    $fileName = $this->fileNameProvider->provide(
                        $result->fileName,
                        $result->fileExtension,
                        null,
                        $scoped,
                    );

                    return $this->mediaService->saveFile(
                        $result->content,
                        $result->fileExtension,
                        $result->mimeType,
                        $fileName,
                        $scoped,
                        self::MEDIA_FOLDER,
                    );
                },
            );
        }

        return $persisted;
    }

    /**
     * @throws DocumentV2Exception
     *
     * @deprecated tag:v6.9.0 - Will be removed once unique index on document(document_number, type_name) is enforced
     */
    private function assertDocumentNumberIsUnique(
        DocumentGenerationRequest $generationRequest,
        string $documentNumber,
        Context $context,
    ): void {
        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('documentNumber', $documentNumber))
            ->addFilter(new EqualsFilter('typeName', $generationRequest->documentType))
            ->setLimit(1);

        $exists = $this->documentRepository->searchIds($criteria, $context)->firstId() !== null;

        if ($exists) {
            throw DocumentV2Exception::documentNumberAlreadyExists($documentNumber);
        }
    }

    /**
     * @throws DocumentV2Exception
     *
     * @deprecated tag:v6.9.0 - Will be removed once `document.document_type_id` is removed
     */
    private function getDocumentTypeId(string $documentType, Context $context): string
    {
        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('technicalName', $documentType))
            ->setLimit(1);

        $id = $this->documentTypeRepository->searchIds($criteria, $context)->firstId();

        if ($id !== null) {
            return $id;
        }

        if (!$this->documentTypeRegistry->supports($documentType)) {
            throw DocumentV2Exception::invalidDocumentType($documentType);
        }

        $sentinelId = $this->documentTypeRepository->searchIds(
            (new Criteria())->addFilter(new EqualsFilter('technicalName', DocumentType::APP_PROVIDED->value))->setLimit(1),
            $context,
        )->firstId();

        if ($sentinelId === null) {
            throw DocumentV2Exception::invalidDocumentType($documentType);
        }

        return $sentinelId;
    }
}
