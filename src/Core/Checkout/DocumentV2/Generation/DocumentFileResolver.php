<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2\Generation;

use Shopware\Core\Checkout\Document\DocumentCollection;
use Shopware\Core\Checkout\Document\DocumentEntity;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('after-sales')]
class DocumentFileResolver
{
    /**
     * @param EntityRepository<DocumentCollection> $documentRepository
     */
    public function __construct(
        private readonly EntityRepository $documentRepository,
    ) {
    }

    public function loadDocument(string $documentId, Context $context): ?DocumentEntity
    {
        $criteria = (new Criteria([$documentId]))
            ->addAssociation('documentFiles.media');

        $document = $this->documentRepository->search($criteria, $context)->getEntities()->first();

        return $document instanceof DocumentEntity ? $document : null;
    }

    public function findMediaByFormat(DocumentEntity $document, string $format): ?MediaEntity
    {
        foreach ($document->getDocumentFiles() ?? [] as $documentFile) {
            if ($documentFile->getDocumentFormat() !== $format) {
                continue;
            }

            return $documentFile->getMedia();
        }

        return null;
    }
}
