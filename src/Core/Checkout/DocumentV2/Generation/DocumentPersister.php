<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2\Generation;

use Shopware\Core\Checkout\Document\Aggregate\DocumentType\DocumentTypeCollection;
use Shopware\Core\Checkout\Document\DocumentCollection;
use Shopware\Core\Checkout\Document\DocumentEntity;
use Shopware\Core\Checkout\DocumentV2\Aggregate\DocumentFile\DocumentFileCollection;
use Shopware\Core\Checkout\DocumentV2\DocumentV2Exception;
use Shopware\Core\Checkout\DocumentV2\Struct\RenderInput;
use Shopware\Core\Checkout\DocumentV2\Struct\RenderState;
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
        Context $context,
    ): DocumentEntity {
        $documentId = Uuid::randomHex();

        // TODO: Keep this guard until the reused document table can enforce document_number + document_type_id uniqueness.
        $this->assertDocumentNumberIsUnique($generationRequest, $input->documentNumber, $context);

        $persistedFiles = $this->writeMediaFiles(
            $state,
            $requestedFormats,
            $context,
        );

        $this->documentRepository->create([
            [
                'id' => $documentId,
                'orderId' => $generationRequest->orderId,
                'orderVersionId' => $generationRequest->orderVersionId,
                'documentTypeId' => $this->getDocumentTypeId($generationRequest, $context),
                'deepLinkCode' => Random::getAlphanumericString(32),
                'config' => [
                    'documentNumber' => $input->documentNumber,
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
        )->first();

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
        string $documentNumber,
        Context $context,
    ): void {
        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('documentNumber', $documentNumber))
            ->addFilter(new EqualsFilter('documentType.technicalName', $generationRequest->documentType))
            ->setLimit(1);

        $exists = $this->documentRepository->searchIds($criteria, $context)->firstId() !== null;

        if ($exists) {
            throw DocumentV2Exception::documentNumberAlreadyExists($documentNumber);
        }
    }

    /**
     * @throws DocumentV2Exception
     */
    private function getDocumentTypeId(DocumentGenerationRequest $generationRequest, Context $context): string
    {
        // TODO: Remove this lookup once document generation no longer stores document types and formats in the database.
        $documentType = $generationRequest->documentType;

        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('technicalName', $documentType))
            ->setLimit(1);

        $documentTypeId = $this->documentTypeRepository->searchIds($criteria, $context)->firstId();

        if ($documentTypeId === null || $documentTypeId === '') {
            throw DocumentV2Exception::documentTypeNotFound($documentType);
        }

        return $documentTypeId;
    }
}
