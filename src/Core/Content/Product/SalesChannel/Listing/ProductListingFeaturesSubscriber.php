<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\Listing;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Product\Events\ProductListingCriteriaEvent;
use Shopware\Core\Content\Product\Events\ProductListingResultEvent;
use Shopware\Core\Content\Product\Events\ProductSearchCriteriaEvent;
use Shopware\Core\Content\Product\Events\ProductSearchResultEvent;
use Shopware\Core\Content\Product\Events\ProductSuggestCriteriaEvent;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Product\SalesChannel\Exception\ProductSortingNotFoundException;
use Shopware\Core\Content\Product\SalesChannel\Sorting\ProductSortingCollection;
use Shopware\Core\Content\Product\SalesChannel\Sorting\ProductSortingEntity;
use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\FetchModeHelper;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\FilterAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\TermsAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\EntityAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\MaxAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\StatsAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Bucket\TermsResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\EntityResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;

class ProductListingFeaturesSubscriber implements EventSubscriberInterface
{
    public const DEFAULT_SEARCH_SORT = 'score';

    /**
     * @var EntityRepositoryInterface
     */
    private $optionRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $sortingRepository;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var SystemConfigService
     */
    private $systemConfigService;

    /**
     * @var ProductListingSortingRegistry
     */
    private $sortingRegistry;

    public function __construct(
        Connection $connection,
        EntityRepositoryInterface $optionRepository,
        EntityRepositoryInterface $productSortingRepository,
        SystemConfigService $systemConfigService,
        ProductListingSortingRegistry $sortingRegistry
    ) {
        $this->optionRepository = $optionRepository;
        $this->sortingRepository = $productSortingRepository;
        $this->connection = $connection;
        $this->systemConfigService = $systemConfigService;
        $this->sortingRegistry = $sortingRegistry;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ProductListingCriteriaEvent::class => [
                ['handleListingRequest', 100],
                ['switchFilter', -100],
            ],
            ProductSuggestCriteriaEvent::class => [
                ['handleSuggestRequest', 100],
                ['switchFilter', -100],
            ],
            ProductSearchCriteriaEvent::class => [
                ['handleSearchRequest', 100],
                ['switchFilter', -100],
            ],
            ProductListingResultEvent::class => [
                ['handleResult', 100],
                ['removeScoreSorting', -100],
            ],
            ProductSearchResultEvent::class => 'handleResult',
        ];
    }

    public function switchFilter(ProductListingCriteriaEvent $event): void
    {
        $request = $event->getRequest();
        $criteria = $event->getCriteria();

        if ($request->get('no-aggregations')) {
            $criteria->resetAggregations();
        }

        // switch all post filters to normal filters to reduce remaining aggregations
        if ($request->get('reduce-aggregations')) {
            foreach ($criteria->getPostFilters() as $filter) {
                $criteria->addFilter($filter);
            }
            $criteria->resetPostFilters();
        }

        if ($request->get('only-aggregations')) {
            // set limit to zero to fetch no products.
            $criteria->setLimit(0);

            // no total count required
            $criteria->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_NONE);

            // sorting and association are only required for the product data
            $criteria->resetSorting();
            $criteria->resetAssociations();
        }
    }

    public function handleSuggestRequest(ProductSuggestCriteriaEvent $event): void
    {
        $criteria = $event->getCriteria();

        // suggestion request supports no aggregations or filters
        $criteria->addAssociation('cover.media');
    }

    public function handleListingRequest(ProductListingCriteriaEvent $event): void
    {
        $request = $event->getRequest();
        $criteria = $event->getCriteria();
        $salesChannelContext = $event->getSalesChannelContext();

        if (!$request->get('order')) {
            $request->request->set('order', $this->getSystemDefaultSorting($salesChannelContext));
        }

        $criteria->addAssociation('cover.media');
        $criteria->addAssociation('options');

        $this->handlePagination($request, $criteria);
        $this->handleFilters($request, $criteria);
        $this->handleSorting($request, $criteria, $salesChannelContext);
    }

    public function handleSearchRequest(ProductSearchCriteriaEvent $event): void
    {
        $request = $event->getRequest();
        $criteria = $event->getCriteria();
        $salesChannelContext = $event->getSalesChannelContext();

        if (!$request->get('order')) {
            $request->request->set('order', self::DEFAULT_SEARCH_SORT);
        }

        $criteria->addAssociation('cover.media');

        $this->handlePagination($request, $criteria);
        $this->handleFilters($request, $criteria);
        $this->handleSorting($request, $criteria, $salesChannelContext);
    }

    public function handleResult(ProductListingResultEvent $event): void
    {
        $this->setGroupedFlag($event);

        $this->groupOptionAggregations($event);

        $this->addCurrentFilters($event);

        $result = $event->getResult();

        /** @var ProductSortingCollection $sortings */
        $sortings = $result->getCriteria()->getExtension('sortings');
        $currentSortingKey = $this->getCurrentSorting($sortings, $event->getRequest())->getKey();

        $result->setSorting($currentSortingKey);
        $result->setAvailableSortings($sortings);
        $result->setPage($this->getPage($event->getRequest()));
        $result->setLimit($this->getLimit($event->getRequest()));
    }

    public function removeScoreSorting(ProductListingResultEvent $event): void
    {
        if (!Feature::isActive('FEATURE_NEXT_5983')) {
            return;
        }

        $sortings = $event->getResult()->getAvailableSortings();

        $defaultSorting = $sortings->getByKey(self::DEFAULT_SEARCH_SORT);
        if ($defaultSorting !== null) {
            $sortings->remove($defaultSorting->getId());
        }

        $event->getResult()->setAvailableSortings($sortings);
    }

    private function handleFilters(Request $request, Criteria $criteria): void
    {
        $this->handleManufacturerFilter($request, $criteria);

        $this->handlePropertyFilter($request, $criteria);

        $this->handlePriceFilter($request, $criteria);

        $this->handleShippingFreeFilter($request, $criteria);

        $this->handleRatingFilter($request, $criteria);
    }

    private function handlePagination(Request $request, Criteria $criteria): void
    {
        $limit = $this->getLimit($request);
        $page = $this->getPage($request);

        $criteria->setOffset(($page - 1) * $limit);
        $criteria->setLimit($limit);
        $criteria->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_EXACT);
    }

    private function handleManufacturerFilter(Request $request, Criteria $criteria): void
    {
        $criteria->addAggregation(
            new EntityAggregation('manufacturer', 'product.manufacturerId', 'product_manufacturer')
        );

        $ids = $this->getManufacturerIds($request);

        if (empty($ids)) {
            return;
        }

        $criteria->addPostFilter(new EqualsAnyFilter('product.manufacturerId', $ids));
    }

    private function handlePropertyFilter(Request $request, Criteria $criteria): void
    {
        $criteria->addAggregation(new TermsAggregation('properties', 'product.properties.id'));
        $criteria->addAggregation(new TermsAggregation('options', 'product.options.id'));

        $ids = $this->getPropertyIds($request);

        if (empty($ids)) {
            return;
        }

        $grouped = $this->connection->fetchAll(
            'SELECT LOWER(HEX(property_group_id)) as property_group_id, LOWER(HEX(id)) as id
             FROM property_group_option
             WHERE id IN (:ids)',
            ['ids' => Uuid::fromHexToBytesList($ids)],
            ['ids' => Connection::PARAM_STR_ARRAY]
        );

        $grouped = FetchModeHelper::group($grouped);

        foreach ($grouped as $options) {
            $options = array_column($options, 'id');

            $criteria->addPostFilter(
                new MultiFilter(
                    MultiFilter::CONNECTION_OR,
                    [
                        new EqualsAnyFilter('product.optionIds', $options),
                        new EqualsAnyFilter('product.propertyIds', $options),
                    ]
                )
            );
        }
    }

    private function handlePriceFilter(Request $request, Criteria $criteria): void
    {
        $criteria->addAggregation(
            new StatsAggregation('price', 'product.listingPrices', true, true, false, false)
        );

        $min = $request->get('min-price');
        $max = $request->get('max-price');

        if (!$min && !$max) {
            return;
        }

        $range = [];
        if ($min > 0) {
            $range[RangeFilter::GTE] = $min;
        }
        if ($max > 0) {
            $range[RangeFilter::LTE] = $max;
        }

        $criteria->addPostFilter(new RangeFilter('product.listingPrices', $range));
    }

    private function handleRatingFilter(Request $request, Criteria $criteria): void
    {
        $criteria->addAggregation(
            new FilterAggregation(
                'rating-exists',
                new MaxAggregation('rating', 'product.ratingAverage'),
                [new RangeFilter('product.ratingAverage', [RangeFilter::GTE => 0])]
            )
        );

        $filtered = $request->get('rating');
        if (!$filtered) {
            return;
        }

        $criteria->addPostFilter(new RangeFilter('product.ratingAverage', [
            RangeFilter::GTE => (int) $filtered,
        ]));
    }

    private function handleShippingFreeFilter(Request $request, Criteria $criteria): void
    {
        $criteria->addAggregation(
            new FilterAggregation(
                'shipping-free-filter',
                new MaxAggregation('shipping-free', 'product.shippingFree'),
                [new EqualsFilter('product.shippingFree', true)]
            )
        );

        $filtered = $request->get('shipping-free');

        if (!$filtered) {
            return;
        }

        $criteria->addPostFilter(new EqualsFilter('product.shippingFree', true));
    }

    private function handleSorting(Request $request, Criteria $criteria, SalesChannelContext $salesChannelContext): void
    {
        /** @var ProductSortingCollection $sortings */
        $sortings = $criteria->getExtension('sortings') ?? new ProductSortingCollection();
        $sortings->merge($this->getAvailableSortings($request, $salesChannelContext->getContext()));

        $currentSorting = $this->getCurrentSorting($sortings, $request);

        $criteria->addSorting(
            ...$currentSorting->createDalSorting()
        );

        $criteria->addExtension('sortings', $sortings);
    }

    /**
     * @throws ProductSortingNotFoundException
     */
    private function getCurrentSorting(ProductSortingCollection $sortings, Request $request): ProductSortingEntity
    {
        $key = $request->get('order');

        $sorting = $sortings->getByKey($key);
        if ($sorting !== null) {
            return $sorting;
        }

        throw new ProductSortingNotFoundException($key);
    }

    private function getAvailableSortings(Request $request, Context $context): EntityCollection
    {
        if (!Feature::isActive('FEATURE_NEXT_5983')) {
            return $this->sortingRegistry->getProductSortingEntities();
        }

        $criteria = new Criteria();
        $availableSortings = $request->get('availableSortings');
        $availableSortingsFilter = [];

        if ($availableSortings) {
            \arsort($availableSortings, SORT_DESC | SORT_NUMERIC);
            $availableSortingsFilter = \array_keys($availableSortings);

            $criteria->addFilter(new EqualsAnyFilter('key', $availableSortingsFilter));
        }

        $criteria
            ->addFilter(new EqualsFilter('active', true))
            ->addSorting(new FieldSorting('priority', 'DESC'));

        /** @var ProductSortingCollection $sortings */
        $sortings = $this->sortingRepository->search($criteria, $context)->getEntities();
        $sortings->merge($this->sortingRegistry->getProductSortingEntities($availableSortings));

        if ($availableSortings) {
            $sortings->sortByKeyArray($availableSortingsFilter);
        }

        return $sortings;
    }

    private function getSystemDefaultSorting(SalesChannelContext $salesChannelContext): string
    {
        return $this->systemConfigService->getString(
            'core.listing.defaultSorting',
            $salesChannelContext->getSalesChannel()->getId()
        );
    }

    private function collectOptionIds(ProductListingResultEvent $event): array
    {
        $aggregations = $event->getResult()->getAggregations();

        /** @var TermsResult|null $properties */
        $properties = $aggregations->get('properties');

        /** @var TermsResult|null $options */
        $options = $aggregations->get('options');

        $options = $options ? $options->getKeys() : [];
        $properties = $properties ? $properties->getKeys() : [];

        return array_unique(array_filter(array_merge($options, $properties)));
    }

    private function setGroupedFlag(ProductListingResultEvent $event): void
    {
        /** @var ProductEntity $product */
        foreach ($event->getResult()->getEntities() as $product) {
            if ($product->getParentId() === null) {
                continue;
            }

            $product->setGrouped(
                $this->isGrouped($event->getRequest(), $product)
            );
        }
    }

    private function isGrouped(Request $request, ProductEntity $product): bool
    {
        if ($product->getMainVariantId() !== null) {
            return false;
        }

        // get all configured expanded groups
        $groups = array_filter(
            (array) $product->getConfiguratorGroupConfig(),
            static function (array $config) {
                return $config['expressionForListings'] ?? false;
            }
        );

        // get ids of groups for later usage
        $groups = array_column($groups, 'id');

        // expanded group count matches option count? All variants are displayed
        if ($product->getOptionIds() !== null && \count($groups) === \count($product->getOptionIds())) {
            return false;
        }

        if ($product->getOptions() === null) {
            return true;
        }

        // get property ids which are applied as filter
        $properties = $this->getPropertyIds($request);

        // now count the configured groups and filtered options
        $count = 0;
        foreach ($product->getOptions() as $option) {
            // check if this option is filtered
            if (\in_array($option->getId(), $properties, true)) {
                ++$count;

                continue;
            }

            // check if the option contained in the expanded groups
            if (\in_array($option->getGroupId(), $groups, true)) {
                ++$count;
            }
        }

        return $count !== \count($product->getOptionIds());
    }

    private function groupOptionAggregations(ProductListingResultEvent $event): void
    {
        $ids = $this->collectOptionIds($event);

        if (empty($ids)) {
            return;
        }

        $criteria = new Criteria($ids);
        $criteria->addAssociation('group');
        $criteria->addAssociation('media');
        $criteria->addFilter(new EqualsFilter('group.filterable', true));
        $criteria->setTitle('product-listing::property-filter');

        /** @var PropertyGroupOptionCollection $options */
        $options = $this->optionRepository->search($criteria, $event->getContext())->getEntities();

        // group options by their property-group
        $grouped = $options->groupByPropertyGroups();
        $grouped->sortByPositions();
        $grouped->sortByConfig();

        $aggregations = $event->getResult()->getAggregations();

        // remove id results to prevent wrong usages
        $aggregations->remove('properties');
        $aggregations->remove('configurators');
        $aggregations->remove('options');
        $aggregations->add(new EntityResult('properties', $grouped));
    }

    private function addCurrentFilters(ProductListingResultEvent $event): void
    {
        $request = $event->getRequest();
        $result = $event->getResult();

        $result->addCurrentFilter('manufacturer', $this->getManufacturerIds($request));

        $result->addCurrentFilter('properties', $this->getPropertyIds($request));

        $result->addCurrentFilter('shipping-free', $request->get('shipping-free'));

        $result->addCurrentFilter('rating', $request->get('rating'));

        $result->addCurrentFilter('price', [
            'min' => $request->get('min-price'),
            'max' => $request->get('max-price'),
        ]);
    }

    private function getManufacturerIds(Request $request): array
    {
        $ids = $request->query->get('manufacturer', '');
        if ($request->isMethod(Request::METHOD_POST)) {
            $ids = $request->request->get('manufacturer', '');
        }

        if (\is_string($ids)) {
            $ids = explode('|', $ids);
        }

        return array_filter($ids);
    }

    private function getPropertyIds(Request $request): array
    {
        $ids = $request->query->get('properties', '');
        if ($request->isMethod(Request::METHOD_POST)) {
            $ids = $request->request->get('properties', '');
        }

        if (\is_string($ids)) {
            $ids = explode('|', $ids);
        }

        return array_filter($ids);
    }

    private function getLimit(Request $request): int
    {
        $limit = $request->query->getInt('limit', 24);

        if ($request->isMethod(Request::METHOD_POST)) {
            $limit = $request->request->getInt('limit', $limit);
        }

        return $limit <= 0 ? 24 : $limit;
    }

    private function getPage(Request $request): int
    {
        $page = $request->query->getInt('p', 1);

        if ($request->isMethod(Request::METHOD_POST)) {
            $page = $request->request->getInt('p', $page);
        }

        return $page <= 0 ? 1 : $page;
    }
}
