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
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
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
     * Field set loaded in listings when `core.listing.partialDataLoading` is enabled. Covers the
     * data required by the default storefront product boxes. Nested association fields (e.g.
     * `prices.ruleId`) must be listed explicitly — a bare association name only loads primary keys.
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

        $partialDataLoading = $this->systemConfigService->get('core.listing.partialDataLoading', $context->getSalesChannelId());

        if ($criteria->getFields() === [] && (bool) $partialDataLoading) {
            $criteria->addFields(self::PARTIAL_LISTING_FIELDS);
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
     *
     * @throws \JsonException
     *
     * @return array<string>
     */
    private function loadPreviews(array $ids, SalesChannelContext $context): array
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
        $displayAsGroup = $this->isDisplayAsGroupEnabled($criteria);

        if ($displayAsGroup) {
            $this->addGrouping($criteria);
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
        $hasOptionFilter = $this->hasOptionFilter($criteria);

        $shouldLoadPreviews = $this->isDisplayAsGroupEnabled($criteria) && $this->shouldLoadPreviews($hasOptionFilter, $criteria, $context);

        if ($shouldLoadPreviews) {
            $mapping = $this->extensions->publish(
                name: LoadPreviewExtension::NAME,
                extension: new LoadPreviewExtension($keys, $context),
                function: $this->loadPreviews(...)
            );
        }

        $event = new ProductListingResolvePreviewEvent($context, $criteria, $mapping, $hasOptionFilter);
        $this->dispatcher->dispatch($event);

        return $event->getMapping();
    }

    private function shouldLoadPreviews(bool $hasOptionFilter, Criteria $criteria, SalesChannelContext $context): bool
    {
        $loadPreview = !$this->systemConfigService->getBool(
            'core.listing.findBestVariant',
            $context->getSalesChannelId()
        );

        if ($hasOptionFilter === true) {
            return $loadPreview;
        }

        $isSearchRoute = $criteria->hasState(ResolvedCriteriaProductSearchRoute::STATE, ProductSuggestRoute::STATE);

        if ($loadPreview && $isSearchRoute) {
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

    private function isDisplayAsGroupEnabled(Criteria $criteria): bool
    {
        return !$criteria->hasState(AbstractProductStreamBuilder::STATE_DISPLAY_AS_GROUP_DISABLED);
    }
}
