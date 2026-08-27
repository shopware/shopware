<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2\Generation;

use Shopware\Core\Checkout\Document\Aggregate\DocumentType\DocumentTypeCollection;
use Shopware\Core\Checkout\Document\DocumentCollection;
use Shopware\Core\Checkout\Document\DocumentEntity;
use Shopware\Core\Checkout\DocumentV2\Aggregate\DocumentFile\DocumentFileCollection;
use Shopware\Core\Checkout\DocumentV2\DocumentFormat;
use Shopware\Core\Checkout\DocumentV2\DocumentV2Exception;
use Shopware\Core\Checkout\DocumentV2\Struct\ReferencedDocument;
use Shopware\Core\Checkout\DocumentV2\Struct\RenderInput;
use Shopware\Core\Checkout\DocumentV2\Struct\RenderState;
use Shopware\Core\Content\Media\File\FileNameProvider;
use Shopware\Core\Content\Media\MediaService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Random;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Persists a generated document and one document_file per requested format, plus the accessible
 * html version whenever it was rendered as a dependency of a requested format.
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
        private FileNameProvider $fileNameProvider,
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
            $this->withAccessibleHtml($requestedFormats, $state),
            $context,
        );

        $documentData = [
            'id' => $documentId,
            'orderId' => $generationRequest->orderId,
            'orderVersionId' => $input->order->getVersionId(),
            'documentTypeId' => $this->getDocumentTypeId($generationRequest, $context), // remove with v6.9.0
            'typeName' => $generationRequest->documentType,
            'documentMediaFileId' => $this->resolvePrimaryMediaId($persistedFiles), // remove with v6.9.0
            'documentA11yMediaFileId' => $persistedFiles[DocumentFormat::HTML->value] ?? null, // remove with v6.9.0
            'referencedDocumentId' => $resolvedReference?->id,
            'deepLinkCode' => Random::getAlphanumericString(32),
            'config' => [
                'documentNumber' => $input->documentNumber,
            ],
        ];

        $this->documentRepository->create([$documentData], $context);

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
     * v1 surfaces attach and download the primary file by default, so html is only used when the document has no other file
     *
     * @param array<string, string> $persistedFiles map<format, mediaId>
     *
     * @deprecated tag:v6.9.0 - Will be removed once `document.document_media_file_id` is removed
     */
    private function resolvePrimaryMediaId(array $persistedFiles): ?string
    {
        if (isset($persistedFiles[DocumentFormat::PDF->value])) {
            return $persistedFiles[DocumentFormat::PDF->value];
        }

        $htmlMediaId = $persistedFiles[DocumentFormat::HTML->value] ?? null;

        unset($persistedFiles[DocumentFormat::HTML->value]);

        return array_values($persistedFiles)[0] ?? $htmlMediaId;
    }

    /**
     * html is the document's accessible version, so it is persisted even when it was
     * only rendered as a dependency of a requested format.
     *
     * @param list<string> $requestedFormats
     *
     * @return list<string>
     */
    private function withAccessibleHtml(array $requestedFormats, RenderState $state): array
    {
        if (\in_array(DocumentFormat::HTML->value, $requestedFormats, true) || !$state->has(DocumentFormat::HTML->value)) {
            return $requestedFormats;
        }

        return [...$requestedFormats, DocumentFormat::HTML->value];
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
    private function getDocumentTypeId(DocumentGenerationRequest $generationRequest, Context $context): string
    {
        $documentType = $generationRequest->documentType;

        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('technicalName', $documentType))
            ->setLimit(1);

        $documentTypeId = $this->documentTypeRepository->searchIds($criteria, $context)->firstId();

        if ($documentTypeId === null) {
            throw DocumentV2Exception::documentTypeNotFound($documentType);
        }

        return $documentTypeId;
    }
}
