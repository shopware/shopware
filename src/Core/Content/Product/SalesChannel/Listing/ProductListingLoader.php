<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\Listing;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Product\Events\ProductListingPreviewCriteriaEvent;
use Shopware\Core\Content\Product\Events\ProductListingResolvePreviewEvent;
use Shopware\Core\Content\Product\Extension\LoadPreviewExtension;
use Shopware\Core\Content\Product\Extension\ResolveListingExtension;
use Shopware\Core\Content\Product\Extension\ResolveListingIdsExtension;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\SalesChannel\AbstractProductCloseoutFilterFactory;
use Shopware\Core\Content\Product\SalesChannel\ProductAvailableFilter;
use Shopware\Core\Content\Product\SalesChannel\Search\ResolvedCriteriaProductSearchRoute;
use Shopware\Core\Content\Product\SalesChannel\Suggest\ProductSuggestRoute;
use Shopware\Core\Content\Product\Util\ExplicitProductIdResolver;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotEqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Grouping\FieldGrouping;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\Extensions\ExtensionDispatcher;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\ArrayEntity;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Package('inventory')]
class ProductListingLoader
{
    /**
     * @internal
     *
     * @param SalesChannelRepository<ProductCollection> $productRepository
     */
    public function __construct(
        private readonly SalesChannelRepository $productRepository,
        private readonly SystemConfigService $systemConfigService,
        private readonly Connection $connection,
        private readonly EventDispatcherInterface $dispatcher,
        private readonly AbstractProductCloseoutFilterFactory $productCloseoutFilterFactory,
        private readonly ExtensionDispatcher $extensions
    ) {
    }

    /**
     * @return EntitySearchResult<ProductCollection>
     */
    public function load(Criteria $origin, SalesChannelContext $context): EntitySearchResult
    {
        // allows full service decoration
        return $this->extensions->publish(
            name: ResolveListingExtension::NAME,
            extension: new ResolveListingExtension($origin, $context),
            function: $this->_load(...)
        );
    }

    /**
     * @return EntitySearchResult<ProductCollection>
     */
    private function _load(Criteria $criteria, SalesChannelContext $context): EntitySearchResult
    {
        $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);
        $clone = clone $criteria;

        $idResult = $this->extensions->publish(
            name: ResolveListingIdsExtension::NAME,
            extension: new ResolveListingIdsExtension($clone, $context),
            function: $this->resolveIds(...)
        );

        $aggregations = $this->productRepository->aggregate($clone, $context);

        $ids = $idResult->getIds();
        // no products found, no need to continue
        if (empty($ids)) {
            $result = new EntitySearchResult(
                ProductDefinition::ENTITY_NAME,
                0,
                new ProductCollection(),
                $aggregations,
                $criteria,
                $context->getContext()
            );

            $result->addState(...$idResult->getStates());
            $result->addExtensions($idResult->getExtensions());

            return $result;
        }

        $mapping = $this->resolvePreviews($ids, $clone, $context);

        $productSearchResult = $this->resolveData($clone, $mapping, $context);

        $this->addExtensions($clone, $idResult, $productSearchResult, $mapping);

        $result = new EntitySearchResult(ProductDefinition::ENTITY_NAME, $idResult->getTotal(), $productSearchResult->getEntities(), $aggregations, $criteria, $context->getContext());
        $result->addState(...$idResult->getStates());
        $result->addExtensions($productSearchResult->getExtensions());

        return $result;
    }

    private function hasOptionFilter(Criteria $criteria): bool
    {
        $filters = $criteria->getPostFilters();

        $fields = [];
        foreach ($filters as $filter) {
            array_push($fields, ...$filter->getFields());
        }

        $fields = array_map(static fn (string $field) => preg_replace('/^product./', '', $field), $fields);

        if (\in_array('options.id', $fields, true)) {
            return true;
        }

        return \in_array('optionIds', $fields, true);
    }

    private function addGrouping(Criteria $criteria): void
    {
        $criteria->addGroupField(new FieldGrouping('displayGroup'));
        $criteria->addFilter(new NotEqualsFilter('displayGroup', null));
    }

    /**
     * @param array<string> $ids
     *
     * @throws \JsonException
     *
     * @return array<string>
     */
    private function loadPreviews(array $ids, SalesChannelContext $context): array
    {
        $ids = $this->createIdentityMapping($ids);

        $config = $this->connection->fetchAllAssociative(
            '# product-listing-loader::resolve-previews
            SELECT
                parent.variant_listing_config as variantListingConfig,
                LOWER(HEX(child.id)) as id,
                LOWER(HEX(parent.id)) as parentId
             FROM product as child
                INNER JOIN product as parent
                    ON parent.id = child.parent_id
                    AND parent.version_id = child.version_id
             WHERE child.version_id = :version
             AND child.id IN (:ids)',
            [
                'ids' => Uuid::fromHexToBytesList(array_values($ids)),
                'version' => Uuid::fromHexToBytes($context->getContext()->getVersionId()),
            ],
            ['ids' => ArrayParameterType::BINARY]
        );

        $mapping = [];
        foreach ($config as $item) {
            if ($item['variantListingConfig'] === null) {
                continue;
            }
            $variantListingConfig = json_decode((string) $item['variantListingConfig'], true, 512, \JSON_THROW_ON_ERROR);

            if (isset($variantListingConfig['mainVariantId']) && $variantListingConfig['mainVariantId']) {
                $mapping[$item['id']] = $variantListingConfig['mainVariantId'];
            }

            if (isset($variantListingConfig['displayParent']) && $variantListingConfig['displayParent']) {
                $mapping[$item['id']] = $item['parentId'];
            }
        }

        // now we have a mapping for "child => main variant"
        if ($mapping === []) {
            return $ids;
        }

        // filter inactive and not available variants
        $criteria = new Criteria(array_values($mapping));
        $criteria->addFilter(new ProductAvailableFilter($context->getSalesChannelId()));

        if ($this->systemConfigService->getBool(
            'core.listing.hideCloseoutProductsWhenOutOfStock',
            $context->getSalesChannelId()
        )) {
            $criteria->addFilter(
                $this->productCloseoutFilterFactory->create($context)
            );
        }

        $this->dispatcher->dispatch(
            new ProductListingPreviewCriteriaEvent($criteria, $context)
        );

        $available = $this->productRepository->searchIds($criteria, $context);

        $remapped = [];
        // replace existing ids with main variant id
        foreach ($ids as $id) {
            // id has no mapped main_variant - keep old id
            if (!isset($mapping[$id])) {
                $remapped[$id] = $id;

                continue;
            }

            // get access to main variant id over the fetched config mapping
            $main = $mapping[$id];

            // main variant is configured but not active/available - keep old id
            if (!$available->has($main)) {
                $remapped[$id] = $id;

                continue;
            }

            // main variant is configured and available - add main variant id
            $remapped[$id] = $main;
        }

        return $remapped;
    }

    /**
     * @param EntitySearchResult<ProductCollection> $productSearchResult
     * @param array<string> $mapping
     */
    private function addExtensions(Criteria $criteria, IdSearchResult $ids, EntitySearchResult $productSearchResult, array $mapping): void
    {
        foreach ($ids->getExtensions() as $name => $extension) {
            $productSearchResult->addExtension($name, $extension);
        }

        if ($criteria->hasState(Criteria::STATE_DISABLE_SEARCH_INFO)) {
            return;
        }

        /** @var string $id */
        foreach ($ids->getIds() as $id) {
            if (!isset($mapping[$id])) {
                continue;
            }

            // current id was mapped to another variant
            if (!$productSearchResult->has($mapping[$id])) {
                continue;
            }

            $product = $productSearchResult->get($mapping[$id]);

            // get access to the data of the search result
            $product->addExtension('search', new ArrayEntity($ids->getDataOfId($id)));
        }
    }

    private function resolveIds(Criteria $criteria, SalesChannelContext $context): IdSearchResult
    {
        $originalCriteria = clone $criteria;
        $explicitProductIds = ExplicitProductIdResolver::fromCriteria($originalCriteria);

        $this->addGrouping($criteria);

        $isSearchRoute = $criteria->hasState(ResolvedCriteriaProductSearchRoute::STATE, ProductSuggestRoute::STATE);

        if ($isSearchRoute && $this->systemConfigService->getBool(
            'core.listing.findBestVariant',
            $context->getSalesChannelId()
        )) {
            $criteria->addState(Criteria::STATE_SCORE_RANKED_GROUPING);
        }

        if ($this->systemConfigService->getBool(
            'core.listing.hideCloseoutProductsWhenOutOfStock',
            $context->getSalesChannelId()
        )) {
            $criteria->addFilter(
                $this->productCloseoutFilterFactory->create($context)
            );
        }

        $groupedResult = $this->productRepository->searchIds($criteria, $context);

        $missingExplicitProductIds = array_values(array_diff($explicitProductIds, $groupedResult->getIds()));
        if ($missingExplicitProductIds === []) {
            return $groupedResult;
        }

        $groupedResultForExplicitMerge = $groupedResult;

        if ($this->hasPagination($originalCriteria)) {
            $groupedResultForExplicitMerge = $this->loadAllGroupedIds($criteria, $context);
            $missingExplicitProductIds = array_values(array_diff($explicitProductIds, $groupedResultForExplicitMerge->getIds()));

            if ($missingExplicitProductIds === []) {
                return $groupedResult;
            }
        }

        $explicitResult = $this->loadExplicitProductIds($originalCriteria, $missingExplicitProductIds, $context);
        if ($explicitResult->getIds() === []) {
            return $groupedResult;
        }

        $mergedResult = $this->mergeIdSearchResults($groupedResultForExplicitMerge, $explicitResult, $explicitProductIds);

        if (!$this->hasPagination($originalCriteria)) {
            return $mergedResult;
        }

        return $this->paginateIdSearchResult($mergedResult, $originalCriteria);
    }

    /**
     * @param list<string> $keys
     *
     * @return array<string, string>
     */
    private function resolvePreviews(array $keys, Criteria $criteria, SalesChannelContext $context): array
    {
        $mapping = $this->createIdentityMapping($keys);

        $explicitProductIds = array_fill_keys(
            array_intersect($keys, ExplicitProductIdResolver::fromCriteria($criteria)),
            true
        );

        $hasOptionFilter = $this->hasOptionFilter($criteria);

        $shouldLoadPreviews = $this->shouldLoadPreviews($criteria, $context);

        if ($shouldLoadPreviews) {
            $mapping = $this->extensions->publish(
                name: LoadPreviewExtension::NAME,
                extension: new LoadPreviewExtension($keys, $context),
                function: $this->loadPreviews(...)
            );
        }

        foreach ($explicitProductIds as $id => $_) {
            $mapping[$id] = $id;
        }

        $event = new ProductListingResolvePreviewEvent($context, $criteria, $mapping, $hasOptionFilter);
        $this->dispatcher->dispatch($event);

        return $event->getMapping();
    }

    private function shouldLoadPreviews(Criteria $criteria, SalesChannelContext $context): bool
    {
        $isSearchRoute = $criteria->hasState(ResolvedCriteriaProductSearchRoute::STATE, ProductSuggestRoute::STATE);

        $shouldLoadPreviewsOnSearch = !$this->systemConfigService->getBool(
            'core.listing.findBestVariant',
            $context->getSalesChannelId()
        );

        if ($shouldLoadPreviewsOnSearch && $isSearchRoute) {
            return true;
        }

        return !$isSearchRoute;
    }

    /**
     * @param array<string, string> $mapping
     *
     * @return EntitySearchResult<ProductCollection>
     */
    private function resolveData(Criteria $criteria, array $mapping, SalesChannelContext $context): EntitySearchResult
    {
        $read = $criteria->cloneForRead(array_values($mapping));
        $read->addAssociation('options.group');

        return $this->productRepository->search($read, $context);
    }

    /**
     * @param list<string> $ids
     *
     * @return array<string, string>
     */
    private function createIdentityMapping(array $ids): array
    {
        $mapping = array_combine($ids, $ids);

        if ($mapping === false) {
            return [];
        }

        return $mapping;
    }

    /**
     * @param list<string> $ids
     */
    private function loadExplicitProductIds(Criteria $criteria, array $ids, SalesChannelContext $context): IdSearchResult
    {
        $criteria = clone $criteria;
        $criteria->setIds($ids);
        $criteria->setOffset(null);
        $criteria->setLimit(null);

        if ($this->systemConfigService->getBool(
            'core.listing.hideCloseoutProductsWhenOutOfStock',
            $context->getSalesChannelId()
        )) {
            $criteria->addFilter(
                $this->productCloseoutFilterFactory->create($context)
            );
        }

        return $this->productRepository->searchIds($criteria, $context);
    }

    private function loadAllGroupedIds(Criteria $criteria, SalesChannelContext $context): IdSearchResult
    {
        $criteria = clone $criteria;
        $criteria->setOffset(null);
        $criteria->setLimit(null);

        return $this->productRepository->searchIds($criteria, $context);
    }

    /**
     * @param list<string> $allExplicitIds
     */
    private function mergeIdSearchResults(IdSearchResult $groupedResult, IdSearchResult $explicitResult, array $allExplicitIds): IdSearchResult
    {
        $groupedIds = $this->getStringIds($groupedResult);
        $resolvedExplicitIds = $this->resolveMergedExplicitIds($groupedIds, $this->getStringIds($explicitResult), $allExplicitIds);
        $displayGroups = $this->loadDisplayGroups([
            ...$groupedIds,
            ...$resolvedExplicitIds,
        ], $groupedResult);

        $explicitProductIds = array_fill_keys($resolvedExplicitIds, true);
        $explicitDisplayGroups = array_fill_keys(
            array_values(array_filter(
                array_map(static fn (string $id): ?string => $displayGroups[$id] ?? null, $resolvedExplicitIds),
                static fn (?string $displayGroup): bool => $displayGroup !== null
            )),
            true
        );

        $data = [];
        $removedGroupedIds = 0;
        foreach ($groupedIds as $id) {
            $displayGroup = $displayGroups[$id] ?? null;

            if ($displayGroup !== null && isset($explicitDisplayGroups[$displayGroup]) && !isset($explicitProductIds[$id])) {
                ++$removedGroupedIds;

                continue;
            }

            $data[$id] = [
                'primaryKey' => $id,
                'data' => $groupedResult->getDataOfId($id),
            ];
        }

        $addedIds = 0;
        foreach ($this->getStringIds($explicitResult) as $id) {
            if (isset($data[$id])) {
                continue;
            }

            $data[$id] = [
                'primaryKey' => $id,
                'data' => $explicitResult->getDataOfId($id),
            ];
            ++$addedIds;
        }

        $result = new IdSearchResult(
            $groupedResult->getTotal() - $removedGroupedIds + $addedIds,
            $data,
            $groupedResult->getCriteria(),
            $groupedResult->getContext()
        );

        $this->copyResultMetaData($result, $groupedResult, $explicitResult);

        return $result;
    }

    /**
     * @param list<string> $groupedIds
     * @param list<string> $loadedExplicitIds
     * @param list<string> $allExplicitIds
     *
     * @return list<string>
     */
    private function resolveMergedExplicitIds(array $groupedIds, array $loadedExplicitIds, array $allExplicitIds): array
    {
        $availableIds = array_fill_keys([
            ...$groupedIds,
            ...$loadedExplicitIds,
        ], true);

        return array_values(array_filter(
            $allExplicitIds,
            static fn (string $id): bool => isset($availableIds[$id])
        ));
    }

    /**
     * @param list<string> $ids
     *
     * @return array<string, string>
     */
    private function loadDisplayGroups(array $ids, IdSearchResult $result): array
    {
        if ($ids === []) {
            return [];
        }

        $rows = $this->connection->fetchAllAssociative(
            'SELECT LOWER(HEX(id)) as id, display_group as displayGroup
             FROM product
             WHERE version_id = :version
             AND id IN (:ids)',
            [
                'version' => Uuid::fromHexToBytes($result->getContext()->getVersionId()),
                'ids' => Uuid::fromHexToBytesList(array_values(array_unique($ids))),
            ],
            ['ids' => ArrayParameterType::BINARY]
        );

        $displayGroups = [];
        foreach ($rows as $row) {
            if (!\is_string($row['id']) || !\is_string($row['displayGroup'])) {
                continue;
            }

            $displayGroups[$row['id']] = $row['displayGroup'];
        }

        return $displayGroups;
    }

    private function paginateIdSearchResult(IdSearchResult $result, Criteria $criteria): IdSearchResult
    {
        $ids = \array_slice(
            $this->getStringIds($result),
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
     * @return list<string>
     */
    private function getStringIds(IdSearchResult $result): array
    {
        return array_values(array_filter($result->getIds(), static fn ($id): bool => \is_string($id)));
    }

    /**
     * @return array<string, array{primaryKey: string, data: array<string, mixed>}>
     */
    private function buildSearchResultData(IdSearchResult $result, ?array $ids = null): array
    {
        $ids ??= $this->getStringIds($result);

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
