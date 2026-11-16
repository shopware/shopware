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
use Shopware\Core\Content\ProductStream\Service\AbstractProductStreamBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\AndFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\Filter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotEqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\OrFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Grouping\FieldGrouping;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
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
     * Criteria state that suppresses the variant grouping (`displayGroup` field grouping) otherwise
     * applied to product listings. Set by {@see AbstractProductStreamBuilder::enrichCriteria()}
     * when a product stream is configured to not display its variants as a group.
     */
    final public const STATE_SKIP_ADD_GROUPING = 'skipAddGrouping';

    /**
     * @deprecated tag:v6.8.0 - Will be removed. Reduced listing loading now uses Criteria::excludeFields()
     *             to drop heavy columns instead of allow-listing fields.
     *
     * @var list<string>
     */
    final public const PARTIAL_LISTING_FIELDS = [
        'id',
        'versionId',
        'parentId',
        'productNumber',
        'displayGroup',
        'states',
        'childCount',
        'name',
        'descriptionTeaser',
        'available',
        'availableStock',
        'stock',
        'isCloseout',
        'minPurchase',
        'maxPurchase',
        'purchaseSteps',
        'purchaseUnit',
        'referenceUnit',
        'unitId',
        'taxId',
        'price',
        'prices.ruleId',
        'prices.price',
        'prices.quantityStart',
        'prices.quantityEnd',
        'cheapestPrice',
        'variantListingConfig',
        'variation',
        'options.group',
        'coverId',
        'cover.media.url',
        'cover.media.alt',
        'cover.media.title',
        'cover.media.mediaTypeRaw',
        'cover.media.thumbnailsRo',
        'manufacturerId',
        'manufacturer.name',
        'ratingAverage',
        'releaseDate',
        'markAsTopseller',
        'deliveryTimeId',
        'deliveryTime.name',
        'deliveryTime.min',
        'deliveryTime.max',
        'deliveryTime.unit',
    ];

    /**
     * Heavy, off-page columns dropped from listings via {@see Criteria::excludeFields()} when
     * `core.listing.partialDataLoading` is enabled.
     *
     * @var list<string>
     */
    final public const PARTIAL_LISTING_EXCLUDED_FIELDS = [
        'description',
        'keywords',
        'customSearchKeywords',
    ];

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

        $partialDataLoading = $this->systemConfigService->getBool('core.listing.partialDataLoading', $context->getSalesChannelId());

        if ($criteria->getFields() === [] && $criteria->getExcludedFields() === [] && $partialDataLoading) {
            $criteria->excludeFields(self::PARTIAL_LISTING_EXCLUDED_FIELDS);
        }

        $clone = clone $criteria;

        $idResult = $this->extensions->publish(
            name: ResolveListingIdsExtension::NAME,
            extension: new ResolveListingIdsExtension($clone, $context),
            function: $this->resolveIds(...)
        );

        $aggregations = $this->productRepository->aggregate($clone, $context);

        $ids = $idResult->getIds();
        // no products found, no need to continue
        if ($ids === []) {
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

        if (\in_array('options.id', $fields, true) || \in_array('properties.id', $fields, true)) {
            return true;
        }

        return \in_array('optionIds', $fields, true) || \in_array('propertyIds', $fields, true);
    }

    private function addGrouping(Criteria $criteria): void
    {
        $criteria->addGroupField(new FieldGrouping('displayGroup'));
        $criteria->addFilter(new NotEqualsFilter('displayGroup', null));
    }

    /**
     * @param array<string> $ids
     * @param list<Filter> $postFilters
     *
     * @throws \JsonException
     *
     * @return array<string>
     */
    private function loadPreviews(array $ids, SalesChannelContext $context, array $postFilters): array
    {
        $ids = array_combine($ids, $ids);

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
        $mainVariantIds = [];
        foreach ($config as $item) {
            if ($item['variantListingConfig'] === null) {
                continue;
            }
            $variantListingConfig = json_decode((string) $item['variantListingConfig'], true, 512, \JSON_THROW_ON_ERROR);

            if (isset($variantListingConfig['mainVariantId']) && $variantListingConfig['mainVariantId']) {
                $mapping[$item['id']] = $variantListingConfig['mainVariantId'];
                $mainVariantIds[$item['id']] = $variantListingConfig['mainVariantId'];
            }

            if (isset($variantListingConfig['displayParent']) && $variantListingConfig['displayParent']) {
                $mapping[$item['id']] = $item['parentId'];
                unset($mainVariantIds[$item['id']]);
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

        // main variant is only used as preview if it matches the post filters
        // parent is always kept, as it represents all of its variants
        if ($postFilters !== [] && $mainVariantIds !== []) {
            $matchesActiveFilters = new AndFilter($postFilters);
            $parentIds = array_values(array_unique(array_diff($mapping, $mainVariantIds)));

            $criteria->addFilter($parentIds === [] ? $matchesActiveFilters : new OrFilter([
                new EqualsAnyFilter('id', $parentIds),
                $matchesActiveFilters,
            ]));
        }

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

            // main variant is configured but not active/available or does not match the post filters - keep old id
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
            if (!$productSearchResult->getEntities()->has($mapping[$id])) {
                continue;
            }

            $product = $productSearchResult->getEntities()->get($mapping[$id]);

            // get access to the data of the search result
            $product->addExtension('search', new ArrayEntity($ids->getDataOfId($id)));
        }
    }

    private function resolveIds(Criteria $criteria, SalesChannelContext $context): IdSearchResult
    {
        $displayAsGroup = !$this->shouldSkipGrouping($criteria);

        if ($displayAsGroup) {
            $this->addGrouping($criteria);
        }

        if ($displayAsGroup && $this->getPriceSortingDirection($criteria, $context) !== null) {
            $criteria->addState(Criteria::STATE_DIRECTIONAL_GROUPED_PRICE_SORTING);
        }

        $isSearchRoute = $criteria->hasState(ResolvedCriteriaProductSearchRoute::STATE, ProductSuggestRoute::STATE);

        if ($displayAsGroup && $isSearchRoute && $this->systemConfigService->getBool(
            'core.listing.findBestVariant',
            $context->getSalesChannelId()
        )) {
            $criteria->addState(Criteria::STATE_SCORE_RANKED_GROUPING);
            $criteria->addFilter(new MultiFilter(MultiFilter::CONNECTION_OR, [
                new EqualsFilter('childCount', 0),
                new EqualsFilter('childCount', null),
            ]));
        }

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

    /**
     * @param list<string> $keys
     *
     * @return array<string, string>
     */
    private function resolvePreviews(array $keys, Criteria $criteria, SalesChannelContext $context): array
    {
        $mapping = array_combine($keys, $keys);

        $shouldLoadPreviews = !$this->shouldSkipGrouping($criteria) && $this->shouldLoadPreviews($criteria, $context);

        $priceSortingDirection = $this->getPriceSortingDirection($criteria, $context);

        if ($shouldLoadPreviews && $priceSortingDirection !== null) {
            $mapping = $this->resolvePriceSortedPreviews($keys, $criteria, $context, $priceSortingDirection);
        } elseif ($shouldLoadPreviews) {
            $mapping = $this->extensions->publish(
                name: LoadPreviewExtension::NAME,
                extension: new LoadPreviewExtension($keys, $context, $criteria->getPostFilters()),
                function: $this->loadPreviews(...)
            );
        }

        $event = new ProductListingResolvePreviewEvent($context, $criteria, $mapping, $this->hasOptionFilter($criteria));
        $this->dispatcher->dispatch($event);

        return $event->getMapping();
    }

    /**
     * With `core.listing.ignorePresentationOnPriceSorting`, a price sorted listing displays the variant which positions the group:
     * its cheapest matching variant ascending and its most expensive matching variant descending.
     * The configured main variant is ignored, groups which display their parent still display it.
     *
     * @param list<string> $keys
     *
     * @return array<string, string>
     */
    private function resolvePriceSortedPreviews(array $keys, Criteria $criteria, SalesChannelContext $context, string $direction): array
    {
        $mapping = array_combine($keys, $keys);

        $parentPreviews = [];
        $displayGroups = [];
        foreach ($this->fetchDisplayGroups($keys, $context) as $row) {
            $config = json_decode((string) ($row['variantListingConfig'] ?? '{}'), true, 512, \JSON_THROW_ON_ERROR);

            if (isset($config['displayParent']) && $config['displayParent']) {
                $parentPreviews[] = $row['id'];

                continue;
            }

            $displayGroups[$row['id']] = $row['displayGroup'];
        }

        if ($parentPreviews !== []) {
            $mapping = [...$mapping, ...$this->extensions->publish(
                name: LoadPreviewExtension::NAME,
                extension: new LoadPreviewExtension($parentPreviews, $context, $criteria->getPostFilters()),
                function: $this->loadPreviews(...)
            )];
        }

        if ($displayGroups === []) {
            return $mapping;
        }

        $variants = clone $criteria;
        $variants->resetGroupFields();
        $variants->resetSorting();
        $variants->resetAggregations();
        $variants->resetAssociations();
        $variants->setLimit(null);
        $variants->setOffset(null);
        $variants->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_NONE);
        $variants->addFilter(new EqualsAnyFilter('displayGroup', array_values(array_unique($displayGroups))));
        $variants->addSorting(new FieldSorting('cheapestPrice', $direction));

        /** @var list<string> $variantIds */
        $variantIds = $this->productRepository->searchIds($variants, $context)->getIds();

        // the display groups are fetched unordered, so the first variant of a group is taken from the search order
        $order = array_flip($variantIds);
        $previews = [];
        foreach ($this->fetchDisplayGroups($variantIds, $context) as $row) {
            $current = $previews[$row['displayGroup']] ?? null;

            if ($current === null || $order[$row['id']] < $order[$current]) {
                $previews[$row['displayGroup']] = $row['id'];
            }
        }

        foreach ($displayGroups as $id => $displayGroup) {
            $mapping[$id] = $previews[$displayGroup] ?? $id;
        }

        return $mapping;
    }

    /**
     * @param list<string> $ids
     *
     * @return list<array{id: string, displayGroup: string, variantListingConfig: string|null}>
     */
    private function fetchDisplayGroups(array $ids, SalesChannelContext $context): array
    {
        if ($ids === []) {
            return [];
        }

        /** @var list<array{id: string, displayGroup: string, variantListingConfig: string|null}> $rows */
        $rows = $this->connection->fetchAllAssociative(
            '# product-listing-loader::fetch-display-groups
            SELECT LOWER(HEX(product.id)) AS id,
                product.display_group AS displayGroup,
                parent.variant_listing_config AS variantListingConfig
            FROM product
                LEFT JOIN product AS parent
                    ON parent.id = product.parent_id
                    AND parent.version_id = product.version_id
            WHERE product.id IN (:ids)
            AND product.version_id = :version',
            [
                'ids' => Uuid::fromHexToBytesList($ids),
                'version' => Uuid::fromHexToBytes($context->getContext()->getVersionId()),
            ],
            ['ids' => ArrayParameterType::BINARY]
        );

        return $rows;
    }

    private function getPriceSortingDirection(Criteria $criteria, SalesChannelContext $context): ?string
    {
        $sorting = array_values($criteria->getSorting())[0] ?? null;

        if ($sorting === null || !\in_array($sorting->getField(), ['cheapestPrice', 'product.cheapestPrice'], true)) {
            return null;
        }

        if (!$this->systemConfigService->getBool('core.listing.ignorePresentationOnPriceSorting', $context->getSalesChannelId())) {
            return null;
        }

        return $sorting->getDirection();
    }

    private function shouldLoadPreviews(Criteria $criteria, SalesChannelContext $context): bool
    {
        $isSearchRoute = $criteria->hasState(ResolvedCriteriaProductSearchRoute::STATE, ProductSuggestRoute::STATE);

        return !$isSearchRoute || !$this->systemConfigService->getBool(
            'core.listing.findBestVariant',
            $context->getSalesChannelId()
        );
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

    private function shouldSkipGrouping(Criteria $criteria): bool
    {
        return $criteria->hasState(self::STATE_SKIP_ADD_GROUPING);
    }
}
