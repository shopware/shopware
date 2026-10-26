<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\ProductAssignment;

use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityCollection;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\ProductStream\Service\AbstractProductStreamBuilder;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\RepositoryIterator;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexerRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Shopware\Core\System\SalesChannel\SalesChannelException;

/**
 * Resolves the products behind explicit ids, dynamic product groups and categories on the server
 * and assigns them to a sales channel, so clients never have to page through product lists themselves.
 *
 * @internal
 */
#[Package('discovery')]
class SalesChannelProductAssigner
{
    private const CHUNK_SIZE = 500;

    /**
     * Every page costs a catalog search plus a visibility write, so a call stops after this many pages
     * and reports itself as incomplete. Repeating the same call continues with the products not written yet.
     */
    private const MAX_PAGES_PER_CALL = 10;

    /**
     * @param EntityRepository<ProductCollection> $productRepository
     * @param EntityRepository<ProductVisibilityCollection> $productVisibilityRepository
     * @param EntityRepository<SalesChannelCollection> $salesChannelRepository
     */
    public function __construct(
        private readonly EntityRepository $productRepository,
        private readonly EntityRepository $productVisibilityRepository,
        private readonly EntityRepository $salesChannelRepository,
        private readonly AbstractProductStreamBuilder $productStreamBuilder,
    ) {
    }

    /**
     * Products that are already visible in the sales channel are skipped.
     * Categories only contribute main products, product groups contribute every matching product including variants.
     *
     * @param list<string> $productIds
     * @param list<string> $productStreamIds
     * @param list<string> $categoryIds
     */
    public function assign(
        string $salesChannelId,
        array $productIds,
        array $productStreamIds,
        array $categoryIds,
        int $visibility,
        Context $context,
    ): SalesChannelProductAssignmentResult {
        if ($productIds === [] && $productStreamIds === [] && $categoryIds === []) {
            return new SalesChannelProductAssignmentResult(0, complete: true);
        }

        $this->ensureSalesChannelExists($salesChannelId, $context);

        /**
         * Queued indexing keeps the product indexer and its cache invalidation out of the request,
         * otherwise every written page would be indexed inline.
         */
        $context = clone $context;
        $context->addState(EntityIndexerRegistry::USE_INDEXING_QUEUE);

        $assigned = 0;
        $remainingPages = self::MAX_PAGES_PER_CALL;
        foreach ($this->buildCriteria($productIds, $productStreamIds, $categoryIds, $context) as $criteria) {
            $criteria->addFilter(
                new NotFilter(NotFilter::CONNECTION_AND, [new EqualsFilter('visibilities.salesChannelId', $salesChannelId)])
            );
            $criteria->setLimit(self::CHUNK_SIZE);

            /**
             * Each page is written before the next one is fetched, so memory stays bound to one page
             * and products assigned by an earlier page, source or call drop out of the following searches.
             */
            $iterator = new RepositoryIterator($this->productRepository, $context, $criteria);
            do {
                if ($remainingPages === 0) {
                    return new SalesChannelProductAssignmentResult($assigned, complete: false);
                }

                $page = $iterator->fetchIds();
                if ($page === null) {
                    break;
                }

                --$remainingPages;
                $assigned += $this->writeVisibilities($page, $salesChannelId, $visibility, $context);
            } while (\count($page) === self::CHUNK_SIZE);
        }

        return new SalesChannelProductAssignmentResult($assigned, complete: true);
    }

    /**
     * @param list<string>|list<array<string, string>> $page
     */
    private function writeVisibilities(array $page, string $salesChannelId, int $visibility, Context $context): int
    {
        $visibilities = [];
        foreach ($page as $productId) {
            if (\is_string($productId)) {
                $visibilities[] = [
                    'id' => Uuid::fromStringToHex($productId . $salesChannelId),
                    'productId' => $productId,
                    'salesChannelId' => $salesChannelId,
                    'visibility' => $visibility,
                ];
            }
        }

        // Deterministic ids let a retry or double submit complete instead of failing on the unique product/sales channel key
        $this->productVisibilityRepository->upsert($visibilities, $context);

        return \count($visibilities);
    }

    /**
     * @param list<string> $productIds
     * @param list<string> $productStreamIds
     * @param list<string> $categoryIds
     *
     * @return list<Criteria>
     */
    private function buildCriteria(array $productIds, array $productStreamIds, array $categoryIds, Context $context): array
    {
        $criteriaList = [];

        if ($productIds !== []) {
            $criteriaList[] = (new Criteria())->addFilter(new EqualsAnyFilter('id', $productIds));
        }

        foreach ($productStreamIds as $productStreamId) {
            $criteria = new Criteria();
            $this->productStreamBuilder->enrichCriteria($criteria, $productStreamId, $context);
            $criteriaList[] = $criteria;
        }

        if ($categoryIds !== []) {
            $criteriaList[] = (new Criteria())
                ->addFilter(new EqualsAnyFilter('categoryIds', $categoryIds))
                ->addFilter(new EqualsFilter('parentId', null));
        }

        return $criteriaList;
    }

    private function ensureSalesChannelExists(string $salesChannelId, Context $context): void
    {
        $criteria = new Criteria([$salesChannelId]);
        $criteria->setLimit(1);

        if ($this->salesChannelRepository->searchIds($criteria, $context)->getTotal() === 0) {
            throw SalesChannelException::salesChannelNotFound($salesChannelId);
        }
    }
}
