<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2\Generation;

use Shopware\Core\Checkout\Document\Aggregate\DocumentType\DocumentTypeCollection;
use Shopware\Core\Checkout\Document\DocumentCollection;
use Shopware\Core\Checkout\Document\DocumentEntity;
use Shopware\Core\Checkout\DocumentV2\Aggregate\DocumentFile\DocumentFileCollection;
use Shopware\Core\Checkout\DocumentV2\DocumentType;
use Shopware\Core\Checkout\DocumentV2\DocumentV2Exception;
use Shopware\Core\Checkout\DocumentV2\Struct\ReferencedDocument;
use Shopware\Core\Checkout\DocumentV2\Struct\RenderInput;
use Shopware\Core\Checkout\DocumentV2\Struct\RenderState;
use Shopware\Core\Checkout\DocumentV2\Type\DocumentTypeRegistry;
use Shopware\Core\Content\Media\MediaService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Random;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Persists a generated document and one document_file per requested format.
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

        $documentTypeId = $this->resolveDocumentTypeId($generationRequest, $context);

        // TODO: Keep this guard until the reused document table can enforce document_number + document_type_id uniqueness.
        $this->assertDocumentNumberIsUnique($generationRequest, $documentTypeId, $input->documentNumber, $context);

        $persistedFiles = $this->writeMediaFiles(
            $state,
            $requestedFormats,
            $context,
        );

        $this->documentRepository->create([
            [
                'id' => $documentId,
                'orderId' => $generationRequest->orderId,
                'orderVersionId' => $input->order->getVersionId(),
                'documentTypeId' => $documentTypeId,
                'referencedDocumentId' => $resolvedReference?->id,
                'deepLinkCode' => Random::getAlphanumericString(32),
                'config' => [
                    'documentNumber' => $input->documentNumber,
                    'documentType' => $generationRequest->documentType,
                ],
            ],
        ], $context);

        $documentFiles = [];

        foreach ($persistedFiles as $format => $mediaId) {
            $documentFiles[] = [
                'id' => Uuid::randomHex(),
                'documentId' => $documentId,
                'documentFormat' => $format,
                'mediaId' => $mediaId,
            ];
        }

        $this->documentFileRepository->create($documentFiles, $context);

        $document = $this->documentRepository->search(
            (new Criteria([$documentId]))->addAssociation('documentFiles.media'),
            $context,
        )->getEntities()->first();

        if (!$document instanceof DocumentEntity) {
            throw DocumentV2Exception::documentNotPersisted($input->documentNumber);
        }

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
                fn (Context $scoped): string => $this->mediaService->saveFile(
                    $result->content,
                    $result->fileExtension,
                    $result->mimeType,
                    $result->fileName,
                    $scoped,
                    self::MEDIA_FOLDER,
                ),
            );
        }

        return $persisted;
    }

    /**
     * @throws DocumentV2Exception
     */
    private function assertDocumentNumberIsUnique(
        DocumentGenerationRequest $generationRequest,
        string $documentTypeId,
        string $documentNumber,
        Context $context,
    ): void {
        // Core types own a dedicated document_type row, so the FK alone disambiguates them. App
        // types all share the `app_provided` sentinel row, so the real identifier stored in the
        // config snapshot is used to keep uniqueness scoped per app document type instead.
        $typeFilter = DocumentType::tryFrom($generationRequest->documentType) !== null
            ? new EqualsFilter('documentTypeId', $documentTypeId)
            : new EqualsFilter('config.documentType', $generationRequest->documentType);

        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('documentNumber', $documentNumber))
            ->addFilter($typeFilter)
            ->setLimit(1);

        $exists = $this->documentRepository->searchIds($criteria, $context)->firstId() !== null;

        if ($exists) {
            throw DocumentV2Exception::documentNumberAlreadyExists($documentNumber);
        }
    }

    /**
     * @throws DocumentV2Exception
     */
    private function resolveDocumentTypeId(DocumentGenerationRequest $generationRequest, Context $context): string
    {
        // TODO: Remove this lookup once document generation no longer stores document types and formats in the database.
        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('technicalName', $generationRequest->documentType))
            ->setLimit(1);

        $id = $this->documentTypeRepository->searchIds($criteria, $context)->firstId();

        if ($id !== null) {
            return $id;
        }

        if (!$this->documentTypeRegistry->supports($generationRequest->documentType)) {
            throw DocumentV2Exception::invalidDocumentType($generationRequest->documentType);
        }

        $sentinelId = $this->documentTypeRepository->searchIds(
            (new Criteria())->addFilter(new EqualsFilter('technicalName', 'app_provided'))->setLimit(1),
            $context,
        )->firstId();

        if ($sentinelId === null) {
            throw DocumentV2Exception::invalidDocumentType($generationRequest->documentType);
        }

        return $sentinelId;
    }
}
