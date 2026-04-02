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

        $matchingExplicitResult = $this->loadMatchingExplicitProductIds($originalCriteria, $missingExplicitProductIds, $context);
        if ($matchingExplicitResult->getIds() === []) {
            return $groupedResult;
        }

        $mergedResult = $this->mergeGroupedAndExplicitResults(
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
     * Grouping collapses each display group to a single representative.
     * When an explicit product selection targets another member of that same display group,
     * we remove the grouped representative and keep the explicitly selected products instead.
     *
     * Example:
     * grouped ids:
     * `[red-l, blue-m]`
     *
     * explicit ids that are still valid for the listing:
     * `[green-l, green-xl]`
     *
     * display groups:
     * `red-l`     => `shirt-parent-a`
     * `green-l`   => `shirt-parent-a`
     * `green-xl`  => `shirt-parent-a`
     * `blue-m`    => `shirt-parent-b`
     *
     * final ids:
     * `[green-l, green-xl, blue-m]`
     *
     * @param IdSearchResult<string> $groupedResult
     * @param IdSearchResult<string> $explicitResult
     * @param list<string> $explicitProductIds
     */
    private function mergeGroupedAndExplicitResults(
        IdSearchResult $groupedResult,
        IdSearchResult $explicitResult,
        array $explicitProductIds,
        SalesChannelContext $context
    ): IdSearchResult {
        $groupedIds = $groupedResult->getIds();
        $resolvedExplicitIds = $this->resolveVisibleExplicitProductIds(
            $groupedIds,
            $explicitResult->getIds(),
            $explicitProductIds
        );
        $displayGroups = $this->loadDisplayGroupsForIds([
            ...$groupedIds,
            ...$resolvedExplicitIds,
        ], $context);

        $explicitProductIds = array_fill_keys($resolvedExplicitIds, true);
        $explicitDisplayGroups = array_fill_keys(
            array_values(array_filter(
                array_map(static fn (string $id): ?string => $displayGroups[$id] ?? null, $resolvedExplicitIds),
                static fn (?string $displayGroup): bool => $displayGroup !== null
            )),
            true
        );

        $data = [];
        foreach ($groupedIds as $id) {
            $displayGroup = $displayGroups[$id] ?? null;

            if ($displayGroup !== null && isset($explicitDisplayGroups[$displayGroup]) && !isset($explicitProductIds[$id])) {
                continue;
            }

            $data[$id] = [
                'primaryKey' => $id,
                'data' => $groupedResult->getDataOfId($id),
            ];
        }

        foreach ($explicitResult->getIds() as $id) {
            if (isset($data[$id])) {
                continue;
            }

            $data[$id] = [
                'primaryKey' => $id,
                'data' => $explicitResult->getDataOfId($id),
            ];
        }

        $result = new IdSearchResult(
            \count($data),
            $data,
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
        $criteria = clone $criteria;
        $criteria->setIds($ids);
        $criteria->setOffset(null);
        $criteria->setLimit(null);

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
        $criteria = clone $criteria;
        $criteria->setOffset(null);
        $criteria->setLimit(null);

        return $this->productRepository->searchIds($criteria, $context);
    }

    /**
     * @param list<string> $groupedIds
     * @param list<string> $loadedExplicitIds
     * @param list<string> $explicitProductIds
     *
     * @return list<string>
     */
    private function resolveVisibleExplicitProductIds(array $groupedIds, array $loadedExplicitIds, array $explicitProductIds): array
    {
        $availableIds = array_fill_keys([
            ...$groupedIds,
            ...$loadedExplicitIds,
        ], true);

        return array_values(array_filter(
            $explicitProductIds,
            static fn (string $id): bool => isset($availableIds[$id])
        ));
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
