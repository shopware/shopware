<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2\Generation;

use Shopware\Core\Checkout\Document\Aggregate\DocumentType\DocumentTypeCollection;
use Shopware\Core\Checkout\Document\DocumentCollection;
use Shopware\Core\Checkout\Document\DocumentEntity;
use Shopware\Core\Checkout\DocumentV2\Aggregate\DocumentFile\DocumentFileCollection;
use Shopware\Core\Checkout\DocumentV2\DocumentV2Exception;
use Shopware\Core\Checkout\DocumentV2\Event\DocumentGeneratedEvent;
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
                'documentTypeId' => $this->getDocumentTypeId($generationRequest->documentType, $context),
                'referencedDocumentId' => $generationRequest->referencedDocumentId,
                'deepLinkCode' => Random::getAlphanumericString(32),
                'config' => [
                    'documentNumber' => $input->documentNumber,
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
                'config' => [
                    'documentNumber' => $documentNumber,
                ],
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
            ->addFilter(new EqualsFilter('config.documentNumber', $documentNumber))
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
    private function getDocumentTypeId(string $documentType, Context $context): string
    {
        // TODO: Remove this lookup once document generation no longer stores document types and formats in the database.
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
