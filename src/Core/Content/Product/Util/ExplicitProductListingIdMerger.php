<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Util;

use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\SalesChannel\AbstractProductCloseoutFilterFactory;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;

/**
 * @internal
 */
#[Package('inventory')]
final class ExplicitProductListingIdMerger
{
    /**
     * @internal
     *
     * @param SalesChannelRepository<ProductCollection> $productRepository
     */
    public function __construct(
        private readonly SalesChannelRepository $productRepository,
        private readonly SystemConfigService $systemConfigService,
        private readonly AbstractProductCloseoutFilterFactory $productCloseoutFilterFactory
    ) {
    }

    /**
     * A grouped listing may return `[main-variant-a, variant-b]`, while the stream explicitly selected `[variant-a-2]`.
     * In that case we reload the missing explicit id and then replace the grouped representative `main-variant-a` with `variant-a-2`.
     *
     * @param IdSearchResult<string> $groupedResult
     * @param list<string> $explicitProductIds
     */
    public function merge(
        IdSearchResult $groupedResult,
        Criteria $groupedCriteria,
        Criteria $originalCriteria,
        array $explicitProductIds,
        SalesChannelContext $context
    ): IdSearchResult {
        $missingExplicitProductIds = $this->findExplicitProductIdsMissingFromListing($explicitProductIds, $groupedResult);
        if ($missingExplicitProductIds === []) {
            return $groupedResult;
        }

        $completeGroupedResult = $groupedResult;
        if ($this->hasPagination($originalCriteria)) {
            $completeGroupedResult = $this->loadAllGroupedListingIds($groupedCriteria, $context);
            $missingExplicitProductIds = $this->findExplicitProductIdsMissingFromListing($explicitProductIds, $completeGroupedResult);

            if ($missingExplicitProductIds === []) {
                return $groupedResult;
            }
        }

        $matchingExplicitResult = $this->loadMatchingExplicitProductIds($originalCriteria, $explicitProductIds, $context);
        if ($matchingExplicitResult->getIds() === []) {
            return $groupedResult;
        }

        $mergedResult = $this->replaceGroupedProductsWithExplicitProducts(
            $completeGroupedResult,
            $matchingExplicitResult,
            $explicitProductIds,
            $context
        );

        if (!$this->hasPagination($originalCriteria)) {
            return $mergedResult;
        }

        return $this->paginateIdSearchResult($mergedResult, $originalCriteria);
    }

    /**
     * Grouping keeps only one product per display group. When explicit selections contain other
     * variants from the same display group, we replace the grouped representative at its current
     * position with the explicitly selected products in their original sort order.
     *
     * Example:
     * grouped ids   = `[red-l, blue-m]`
     * explicit ids  = `[green-l, green-xl]`
     * display group = `shirt-parent-a` for `red-l`, `green-l`, `green-xl`
     * final ids     = `[green-l, green-xl, blue-m]`
     *
     * @param IdSearchResult<string> $groupedResult
     * @param IdSearchResult<string> $explicitResult
     * @param list<string> $explicitProductIds
     */
    private function replaceGroupedProductsWithExplicitProducts(
        IdSearchResult $groupedResult,
        IdSearchResult $explicitResult,
        array $explicitProductIds,
        SalesChannelContext $context
    ): IdSearchResult {
        $groupedIds = $groupedResult->getIds();
        $matchingExplicitIds = $this->matchExplicitProductIdsInSortOrder($explicitResult->getIds(), $explicitProductIds);
        $displayGroups = $this->loadDisplayGroupsForIds([
            ...$groupedIds,
            ...$matchingExplicitIds,
        ], $context);
        [$replacementIdsByGroupedId, $trailingExplicitIds] = $this->buildExplicitReplacementMap(
            $groupedIds,
            $matchingExplicitIds,
            $displayGroups
        );

        $mergedData = [];
        foreach ($groupedIds as $groupedId) {
            $replacementIds = $replacementIdsByGroupedId[$groupedId] ?? null;
            if ($replacementIds !== null) {
                $this->appendIdsFromResult($mergedData, $explicitResult, $replacementIds);

                continue;
            }

            $mergedData[$groupedId] = [
                'primaryKey' => $groupedId,
                'data' => $groupedResult->getDataOfId($groupedId),
            ];
        }

        $this->appendIdsFromResult($mergedData, $explicitResult, $trailingExplicitIds);

        $result = new IdSearchResult(
            \count($mergedData),
            $mergedData,
            $groupedResult->getCriteria(),
            $groupedResult->getContext()
        );

        $this->copyResultMetaData($result, $groupedResult, $explicitResult);

        return $result;
    }

    /**
     * @param list<string> $ids
     */
    private function loadMatchingExplicitProductIds(Criteria $criteria, array $ids, SalesChannelContext $context): IdSearchResult
    {
        $criteria = $this->cloneForUnboundedIdReload($criteria);
        $criteria->setIds($ids);

        if ($this->systemConfigService->getBool(
            'core.listing.hideCloseoutProductsWhenOutOfStock',
            $context->getSalesChannelId()
        )) {
            $criteria->addFilter($this->productCloseoutFilterFactory->create($context));
        }

        return $this->productRepository->searchIds($criteria, $context);
    }

    private function loadAllGroupedListingIds(Criteria $criteria, SalesChannelContext $context): IdSearchResult
    {
        $criteria = $this->cloneForUnboundedIdReload($criteria);

        return $this->productRepository->searchIds($criteria, $context);
    }

    private function cloneForUnboundedIdReload(Criteria $criteria): Criteria
    {
        $criteria = clone $criteria;
        $criteria->setOffset(null);
        $criteria->setLimit(null);
        $criteria->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_NONE);

        return $criteria;
    }

    /**
     * @param list<string> $loadedExplicitIds
     * @param list<string> $explicitProductIds
     *
     * @return list<string>
     */
    private function matchExplicitProductIdsInSortOrder(array $loadedExplicitIds, array $explicitProductIds): array
    {
        return array_values(array_intersect($loadedExplicitIds, $explicitProductIds));
    }

    /**
     * @param list<string> $explicitProductIds
     * @param IdSearchResult<string> $result
     *
     * @return list<string>
     */
    private function findExplicitProductIdsMissingFromListing(array $explicitProductIds, IdSearchResult $result): array
    {
        $resultIds = $result->getIds();

        return array_values(array_diff($explicitProductIds, $resultIds));
    }

    /**
     * @param list<string> $ids
     *
     * @return array<string, string>
     */
    private function loadDisplayGroupsForIds(array $ids, SalesChannelContext $context): array
    {
        if ($ids === []) {
            return [];
        }

        $criteria = new Criteria(array_values(array_unique($ids)));
        $criteria->addFields(['id', 'displayGroup']);

        $products = $this->productRepository->search($criteria, $context)->getEntities();

        $displayGroups = [];
        foreach ($products as $product) {
            $displayGroup = $product->get('displayGroup');
            if (!\is_string($displayGroup)) {
                continue;
            }

            $id = $product->getUniqueIdentifier();
            if ($id === '') {
                continue;
            }

            $displayGroups[$id] = $displayGroup;
        }

        return $displayGroups;
    }

    /**
     * @param list<string> $groupedIds
     * @param list<string> $explicitIds
     * @param array<string, string> $displayGroups
     *
     * @return array{0: array<string, list<string>>, 1: list<string>}
     */
    private function buildExplicitReplacementMap(array $groupedIds, array $explicitIds, array $displayGroups): array
    {
        $groupedRepresentativeIdsByDisplayGroup = [];
        foreach ($groupedIds as $groupedId) {
            $displayGroup = $displayGroups[$groupedId] ?? null;
            if ($displayGroup === null || isset($groupedRepresentativeIdsByDisplayGroup[$displayGroup])) {
                continue;
            }

            $groupedRepresentativeIdsByDisplayGroup[$displayGroup] = $groupedId;
        }

        $replacementIdsByGroupedId = [];
        $trailingExplicitIds = [];

        foreach ($explicitIds as $id) {
            $displayGroup = $displayGroups[$id] ?? null;
            if ($displayGroup === null || !isset($groupedRepresentativeIdsByDisplayGroup[$displayGroup])) {
                $trailingExplicitIds[] = $id;

                continue;
            }

            $replacementIdsByGroupedId[$groupedRepresentativeIdsByDisplayGroup[$displayGroup]][] = $id;
        }

        return [$replacementIdsByGroupedId, $trailingExplicitIds];
    }

    /**
     * @param array<string, array{primaryKey: string, data: array<string, mixed>}> $data
     * @param IdSearchResult<string> $explicitResult
     * @param list<string> $ids
     */
    private function appendIdsFromResult(array &$data, IdSearchResult $explicitResult, array $ids): void
    {
        foreach ($ids as $id) {
            if (isset($data[$id])) {
                continue;
            }

            $data[$id] = [
                'primaryKey' => $id,
                'data' => $explicitResult->getDataOfId($id),
            ];
        }
    }

    /**
     * @param IdSearchResult<string> $result
     */
    private function paginateIdSearchResult(IdSearchResult $result, Criteria $criteria): IdSearchResult
    {
        $resultIds = $result->getIds();

        $ids = \array_slice(
            $resultIds,
            $criteria->getOffset() ?? 0,
            $criteria->getLimit()
        );

        $paginatedResult = new IdSearchResult(
            $result->getTotal(),
            $this->buildSearchResultData($result, $ids),
            $criteria,
            $result->getContext()
        );

        $this->copyResultMetaData($paginatedResult, $result);

        return $paginatedResult;
    }

    private function hasPagination(Criteria $criteria): bool
    {
        return $criteria->getOffset() !== null || $criteria->getLimit() !== null;
    }

    private function copyResultMetaData(IdSearchResult $target, IdSearchResult ...$sources): void
    {
        $states = [];
        foreach ($sources as $source) {
            array_push($states, ...$source->getStates());
        }

        $states = array_values(array_unique($states));
        if ($states !== []) {
            $target->addState(...$states);
        }

        foreach ($sources as $source) {
            foreach ($source->getExtensions() as $name => $extension) {
                if (!$target->hasExtension($name)) {
                    $target->addExtension($name, $extension);
                }
            }
        }
    }

    /**
     * @param IdSearchResult<string> $result
     * @param list<string> $ids
     *
     * @return array<string, array{primaryKey: string, data: array<string, mixed>}>
     */
    private function buildSearchResultData(IdSearchResult $result, array $ids): array
    {
        $data = [];
        foreach ($ids as $id) {
            $data[$id] = [
                'primaryKey' => $id,
                'data' => $result->getDataOfId($id),
            ];
        }

        return $data;
    }
}
