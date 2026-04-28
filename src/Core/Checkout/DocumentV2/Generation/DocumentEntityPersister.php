<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2\Generation;

use Shopware\Core\Checkout\Document\Aggregate\DocumentType\DocumentTypeCollection;
use Shopware\Core\Checkout\Document\DocumentCollection;
use Shopware\Core\Checkout\Document\DocumentEntity;
use Shopware\Core\Checkout\DocumentV2\Aggregate\DocumentFile\DocumentFileCollection;
use Shopware\Core\Checkout\DocumentV2\DocumentV2Exception;
use Shopware\Core\Checkout\DocumentV2\Struct\RenderInput;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Random;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Persists the generated document aggregate after rendering finished successfully.
 *
 * One document row represents the shared document number and order snapshot, while each
 * requested output format is stored as a separate document_file linked to the same document.
 *
 * @internal
 */
#[Package('after-sales')]
final readonly class DocumentEntityPersister
{
    /**
     * @param EntityRepository<DocumentCollection> $documentRepository
     * @param EntityRepository<DocumentFileCollection> $documentFileRepository
     * @param EntityRepository<DocumentTypeCollection> $documentTypeRepository
     */
    public function __construct(
        private EntityRepository $documentRepository,
        private EntityRepository $documentFileRepository,
        private EntityRepository $documentTypeRepository,
    ) {
    }

    /**
     * @param array<string, string> $persistedFiles
     *
     * @throws DocumentV2Exception
     */
    public function persist(DocumentGenerationRequest $generationRequest, RenderInput $input, array $persistedFiles): DocumentEntity
    {
        $documentId = Uuid::randomHex();

        // TODO: Keep this guard until the reused document table can enforce document_number + document_type_id uniqueness.
        $this->assertDocumentNumberIsUnique($generationRequest, $input->documentNumber);

        $this->documentRepository->create([
            [
                'id' => $documentId,
                'orderId' => $generationRequest->orderId,
                'orderVersionId' => $generationRequest->orderVersionId,
                'documentTypeId' => $this->getDocumentTypeId($generationRequest),
                'documentNumber' => $input->documentNumber,
                'deepLinkCode' => Random::getAlphanumericString(32),
                'config' => [],
            ],
        ], $generationRequest->apiContext);

        $documentFiles = [];

        foreach ($persistedFiles as $format => $mediaId) {
            $documentFiles[] = [
                'id' => Uuid::randomHex(),
                'documentId' => $documentId,
                'documentFormat' => $format,
                'mediaId' => $mediaId,
            ];
        }

        $this->documentFileRepository->create($documentFiles, $generationRequest->apiContext);

        $document = $this->documentRepository->search(
            (new Criteria([$documentId]))->addAssociation('documentFiles.media'),
            $generationRequest->apiContext,
        )->first();

        if (!$document instanceof DocumentEntity) {
            throw DocumentV2Exception::documentNotPersisted($input->documentNumber);
        }

        return $document;
    }

    /**
     * @throws DocumentV2Exception
     */
    private function assertDocumentNumberIsUnique(DocumentGenerationRequest $generationRequest, string $documentNumber): void
    {
        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('documentNumber', $documentNumber))
            ->addFilter(new EqualsFilter('documentType.technicalName', $generationRequest->documentType))
            ->setLimit(1);

        $exists = $this->documentRepository->searchIds($criteria, $generationRequest->apiContext)->firstId() !== null;

        if ($exists) {
            throw DocumentV2Exception::documentNumberAlreadyExists($documentNumber);
        }
    }

    /**
     * @throws DocumentV2Exception
     */
    private function getDocumentTypeId(DocumentGenerationRequest $generationRequest): string
    {
        // TODO: Remove this lookup once document generation no longer stores document types and formats in the database.
        $documentType = $generationRequest->documentType;
        $context = $generationRequest->apiContext;

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
