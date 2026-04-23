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
     * @param array<string, string> $files
     *
     * @throws DocumentV2Exception
     */
    public function persist(DocumentGenerationContext $generationContext, RenderInput $input, array $files): DocumentEntity
    {
        $documentId = Uuid::randomHex();

        $this->assertDocumentNumberIsUnique($generationContext, $input->documentNumber);

        $this->documentRepository->create([
            [
                'id' => $documentId,
                'orderId' => $generationContext->getOrderId(),
                'orderVersionId' => $generationContext->getOrderVersionId(),
                'documentTypeId' => $this->getDocumentTypeId($generationContext),
                'documentNumber' => $input->documentNumber,
                'deepLinkCode' => Random::getAlphanumericString(32),
                'config' => [],
            ],
        ], $generationContext->getContext());

        $documentFiles = [];

        foreach ($files as $format => $mediaId) {
            $documentFiles[] = [
                'id' => Uuid::randomHex(),
                'documentId' => $documentId,
                'documentFormat' => $format,
                'mediaId' => $mediaId,
            ];
        }

        $this->documentFileRepository->create($documentFiles, $generationContext->getContext());

        $document = $this->documentRepository->search(
            (new Criteria([$documentId]))->addAssociation('documentFiles.media'),
            $generationContext->getContext(),
        )->first();

        if (!$document instanceof DocumentEntity) {
            throw DocumentV2Exception::documentNotPersisted($input->documentNumber);
        }

        return $document;
    }

    /**
     * @throws DocumentV2Exception
     */
    private function assertDocumentNumberIsUnique(DocumentGenerationContext $generationContext, string $documentNumber): void
    {
        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('documentNumber', $documentNumber))
            ->addFilter(new EqualsFilter('documentType.technicalName', $generationContext->getDocumentType()))
            ->setLimit(1);

        $exists = $this->documentRepository->searchIds($criteria, $generationContext->getContext())->firstId() !== null;

        if ($exists) {
            throw DocumentV2Exception::documentNumberAlreadyExists($documentNumber);
        }
    }

    /**
     * @throws DocumentV2Exception
     */
    private function getDocumentTypeId(DocumentGenerationContext $generationContext): string
    {
        $documentType = $generationContext->getDocumentType();
        $context = $generationContext->getContext();

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
