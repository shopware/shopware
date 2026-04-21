<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2\Generation;

use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Document\DocumentCollection;
use Shopware\Core\Checkout\Document\DocumentEntity;
use Shopware\Core\Checkout\DocumentV2\Aggregate\DocumentFile\DocumentFileCollection;
use Shopware\Core\Checkout\DocumentV2\DocumentV2Exception;
use Shopware\Core\Checkout\DocumentV2\Struct\RenderInput;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
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
     */
    public function __construct(
        private EntityRepository $documentRepository,
        private EntityRepository $documentFileRepository,
        private Connection $connection,
    ) {
    }

    /**
     * @param array<string, string> $files
     */
    public function persist(DocumentGenerationContext $generationContext, RenderInput $input, array $files): DocumentEntity
    {
        $documentId = Uuid::randomHex();

        $this->documentRepository->create([
            [
                'id' => $documentId,
                'orderId' => $generationContext->getOrderId(),
                'orderVersionId' => $generationContext->getOrderVersionId(),
                'documentTypeId' => $this->getDocumentTypeId($generationContext->getDocumentType()),
                'documentNumber' => $input->getDocumentNumber(),
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
            throw DocumentV2Exception::documentNotPersisted($documentId);
        }

        return $document;
    }

    private function getDocumentTypeId(string $documentType): string
    {
        $documentTypeId = $this->connection->fetchOne(
            'SELECT LOWER(HEX(id)) as id FROM document_type WHERE technical_name = :technicalName',
            ['technicalName' => $documentType],
        );

        if (!\is_string($documentTypeId) || $documentTypeId === '') {
            throw DocumentV2Exception::documentTypeNotFound($documentType);
        }

        return $documentTypeId;
    }
}
