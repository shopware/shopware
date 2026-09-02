<?php declare(strict_types=1);

namespace Shopware\Core\Content\Shared\MailFlow;

use Shopware\Core\Checkout\Document\DocumentCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Log\Package;

/**
 * Answers which documents belong to a mail, and in which formats.
 *
 * The mail body (together with the accessible document links) is rendered before its attachments are built,
 * so the documents are needed twice at two different points: once by {@see \Shopware\Core\Content\Flow\Dispatching\Storer\A11yRenderedDocumentStorer}
 * to link the accessible html in the body, and once by {@see \Shopware\Core\Content\Mail\Service\MailAttachmentsBuilder} to attach the files.
 * Both use this class so that the body and the attachments can never disagree about the document set.
 *
 * @internal
 *
 * @phpstan-type ResolvedDocuments array<string, list<string>|null>
 */
#[Package('after-sales')]
final readonly class DocumentResolver
{
    /**
     * @param EntityRepository<DocumentCollection> $documentRepository
     */
    public function __construct(
        private EntityRepository $documentRepository,
    ) {
    }

    /**
     * @param array<string, mixed> $eventConfig
     * @param array<string> $preResolvedIds document ids the caller already resolved
     *
     * @return ResolvedDocuments map<documentId, requestedFormats>, null meaning every generated format
     */
    public function resolve(array $eventConfig, array $preResolvedIds, ?string $orderId, Context $context): array
    {
        $documentTypeIds = $eventConfig['documentTypeIds'] ?? null;

        // Branches on the config shape rather than the feature flag, so already-saved sequences keep
        // working the way they were configured even if the flag is toggled afterward.
        if ($orderId !== null && $orderId !== '' && \is_array($documentTypeIds) && $documentTypeIds !== []) {
            return $this->resolveByDocumentTypeIds($orderId, $documentTypeIds, $preResolvedIds, $context);
        }

        return $this->resolveByDocumentType($eventConfig, $preResolvedIds, $orderId, $context);
    }

    /**
     * v1 config shape: the latest document per configured type, union with whatever the caller
     * already resolved. Formats were not configurable, so every generated format applies.
     *
     * @deprecated tag:v6.9.0 - Remove this method, the v1 config shape is deprecated and will be removed in v6.9.0
     *
     * @param array<string> $documentTypeIds
     * @param array<string> $preResolvedIds
     *
     * @return ResolvedDocuments
     */
    private function resolveByDocumentTypeIds(string $orderId, array $documentTypeIds, array $preResolvedIds, Context $context): array
    {
        $documentIds = array_merge($preResolvedIds, $this->getLatestDocumentsOfTypes($orderId, $documentTypeIds, $context));

        return array_fill_keys($documentIds, null);
    }

    /**
     * v2 config shape: the latest document of the configured type, restricted to the configured formats.
     *
     * @param array<string, mixed> $eventConfig
     * @param array<string> $preResolvedIds
     *
     * @return ResolvedDocuments
     */
    private function resolveByDocumentType(array $eventConfig, array $preResolvedIds, ?string $orderId, Context $context): array
    {
        // document ids the caller already resolved always cover every generated format
        $resolved = array_fill_keys($preResolvedIds, null);

        $documentType = $eventConfig['documentType'] ?? null;

        if ($orderId === null || $orderId === '' || !\is_string($documentType) || $documentType === '') {
            return $resolved;
        }

        $documentId = $this->getLatestDocumentIdByTechnicalName($orderId, $documentType, $context);

        if ($documentId === null || \array_key_exists($documentId, $resolved)) {
            return $resolved;
        }

        $fileFormats = $eventConfig['fileFormats'] ?? [];

        $resolved[$documentId] = \is_array($fileFormats) && $fileFormats !== [] ? array_values($fileFormats) : null;

        return $resolved;
    }

    /**
     * `documentTypeId` is deprecated in favour of `typeName`, but the v1 config shape stores type ids,
     * so it stays the filter for as long as that shape is supported.
     *
     * @param array<string> $documentTypeIds
     *
     * @return array<string>
     */
    private function getLatestDocumentsOfTypes(string $orderId, array $documentTypeIds, Context $context): array
    {
        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('orderId', $orderId))
            ->addFilter(new EqualsAnyFilter('documentTypeId', $documentTypeIds))
            ->addSorting(new FieldSorting('createdAt', FieldSorting::ASCENDING));
        $criteria->setTitle('send-mail::latest-documents-by-type');

        $latestPerType = [];

        // sorted ascending, so the newest document of a type overwrites the older ones
        foreach ($this->documentRepository->search($criteria, $context)->getEntities() as $document) {
            $latestPerType[$document->getDocumentTypeId()] = $document->getId();
        }

        return array_values($latestPerType);
    }

    private function getLatestDocumentIdByTechnicalName(string $orderId, string $documentTypeTechnicalName, Context $context): ?string
    {
        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('orderId', $orderId))
            ->addFilter(new EqualsFilter('typeName', $documentTypeTechnicalName))
            ->addSorting(new FieldSorting('createdAt', FieldSorting::DESCENDING))
            ->setLimit(1);
        $criteria->setTitle('send-mail::latest-document-by-type');

        return $this->documentRepository->searchIds($criteria, $context)->firstId();
    }
}
