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
use Shopware\Core\System\SalesChannel\ProductAssignment\Api\SalesChannelProductAssignmentRequest;
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
     */
    public function assign(
        string $salesChannelId,
        SalesChannelProductAssignmentRequest $request,
        Context $context,
    ): SalesChannelProductAssignmentResult {
        if ($request->productIds === [] && $request->productStreamIds === [] && $request->categoryIds === []) {
            return new SalesChannelProductAssignmentResult(0, complete: true);
        }

        $this->ensureSalesChannelExists($salesChannelId, $context);

        /**
         * Queued indexing keeps the product indexer and its cache invalidation out of the request,
         * otherwise every written page would be indexed inline.
         */
        $queuedIndexingContext = clone $context;
        $queuedIndexingContext->addState(EntityIndexerRegistry::USE_INDEXING_QUEUE);

        $assigned = 0;
        $remainingPages = self::MAX_PAGES_PER_CALL;
        foreach ($this->buildCriteria($request, $queuedIndexingContext) as $criteria) {
            $criteria->addFilter(
                new NotFilter(NotFilter::CONNECTION_AND, [new EqualsFilter('visibilities.salesChannelId', $salesChannelId)])
            );
            $criteria->setLimit(self::CHUNK_SIZE);

            /**
             * Each page is written before the next one is fetched, so memory stays bound to one page
             * and products assigned by an earlier page, source or call drop out of the following searches.
             */
            $iterator = new RepositoryIterator($this->productRepository, $queuedIndexingContext, $criteria);
            do {
                if ($remainingPages === 0) {
                    return new SalesChannelProductAssignmentResult($assigned, complete: false);
                }

                $page = $iterator->fetchIds();
                if ($page === null) {
                    break;
                }

                --$remainingPages;
                $assigned += $this->writeVisibilities($page, $salesChannelId, $request->visibility, $queuedIndexingContext);
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
     * @return list<Criteria>
     */
    private function buildCriteria(SalesChannelProductAssignmentRequest $request, Context $context): array
    {
        $criteriaList = [];

        if ($request->productIds !== []) {
            $criteriaList[] = (new Criteria())->addFilter(new EqualsAnyFilter('id', $request->productIds));
        }

        foreach ($request->productStreamIds as $productStreamId) {
            $criteria = new Criteria();
            $this->productStreamBuilder->enrichCriteria($criteria, $productStreamId, $context);
            $criteriaList[] = $criteria;
        }

        if ($request->categoryIds !== []) {
            $criteriaList[] = (new Criteria())
                ->addFilter(new EqualsAnyFilter('categoryIds', $request->categoryIds))
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
