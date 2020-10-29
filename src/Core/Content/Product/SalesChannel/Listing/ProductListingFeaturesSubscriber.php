<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\Listing;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Product\Events\ProductListingCollectFilterEvent;
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
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

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

    /**
     * @var EventDispatcherInterface
     */
    private $dispatcher;

    public function __construct(
        Connection $connection,
        EntityRepositoryInterface $optionRepository,
        EntityRepositoryInterface $productSortingRepository,
        SystemConfigService $systemConfigService,
        ProductListingSortingRegistry $sortingRegistry,
        EventDispatcherInterface $dispatcher
    ) {
        $this->optionRepository = $optionRepository;
        $this->sortingRepository = $productSortingRepository;
        $this->connection = $connection;
        $this->systemConfigService = $systemConfigService;
        $this->sortingRegistry = $sortingRegistry;
        $this->dispatcher = $dispatcher;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ProductListingCriteriaEvent::class => [
                ['handleListingRequest', 100],
                ['handleFlags', -100],
            ],
            ProductSuggestCriteriaEvent::class => [
                ['handleFlags', -100],
            ],
            ProductSearchCriteriaEvent::class => [
                ['handleSearchRequest', 100],
                ['handleFlags', -100],
            ],
            ProductListingResultEvent::class => [
                ['handleResult', 100],
                ['removeScoreSorting', -100],
            ],
            ProductSearchResultEvent::class => 'handleResult',
        ];
    }

    public function handleFlags(ProductListingCriteriaEvent $event): void
    {
        $request = $event->getRequest();
        $criteria = $event->getCriteria();

        if ($request->get('no-aggregations')) {
            $criteria->resetAggregations();
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

    public function handleListingRequest(ProductListingCriteriaEvent $event): void
    {
        $request = $event->getRequest();
        $criteria = $event->getCriteria();
        $context = $event->getSalesChannelContext();

        if (!$request->get('order')) {
            $request->request->set('order', $this->getSystemDefaultSorting($context));
        }

        $criteria->addAssociation('options');

        $this->handlePagination($request, $criteria);

        $this->handleFilters($request, $criteria, $context);

        $this->handleSorting($request, $criteria, $context);
    }

    public function handleSearchRequest(ProductSearchCriteriaEvent $event): void
    {
        $request = $event->getRequest();
        $criteria = $event->getCriteria();
        $context = $event->getSalesChannelContext();

        if (!$request->get('order')) {
            $request->request->set('order', self::DEFAULT_SEARCH_SORT);
        }

        $this->handlePagination($request, $criteria);

        $this->handleFilters($request, $criteria, $context);

        $this->handleSorting($request, $criteria, $context);
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
        $sortings = $event->getResult()->getAvailableSortings();

        $defaultSorting = $sortings->getByKey(self::DEFAULT_SEARCH_SORT);
        if ($defaultSorting !== null) {
            $sortings->remove($defaultSorting->getId());
        }

        $event->getResult()->setAvailableSortings($sortings);
    }

    private function handleFilters(Request $request, Criteria $criteria, SalesChannelContext $context): void
    {
        $criteria->addAssociation('manufacturer');

        $filters = $this->getFilters($request, $context);

        $aggregations = $this->getAggregations($request, $filters);

        foreach ($aggregations as $aggregation) {
            $criteria->addAggregation($aggregation);
        }

        foreach ($filters as $filter) {
            if ($filter->isFiltered()) {
                $criteria->addPostFilter($filter->getFilter());
            }
        }

        $criteria->addExtension('filters', $filters);
    }

    private function getAggregations(Request $request, FilterCollection $filters): array
    {
        $aggregations = [];

        if ($request->get('reduce-aggregations', null) === null) {
            foreach ($filters as $filter) {
                $aggregations = array_merge($aggregations, $filter->getAggregations());
            }

            return $aggregations;
        }

        foreach ($filters as $filter) {
            $excluded = $filters->filtered();

            if ($filter->exclude()) {
                $excluded = $excluded->blacklist($filter->getName());
            }

            foreach ($filter->getAggregations() as $aggregation) {
                if ($aggregation instanceof FilterAggregation) {
                    $aggregation->addFilters($excluded->getFilters());

                    $aggregations[] = $aggregation;

                    continue;
                }

                $aggregation = new FilterAggregation(
                    $aggregation->getName() . '-filtered',
                    $aggregation,
                    $excluded->getFilters()
                );

                $aggregations[] = $aggregation;
            }
        }

        return $aggregations;
    }

    private function handlePagination(Request $request, Criteria $criteria): void
    {
        $limit = $this->getLimit($request);
        $page = $this->getPage($request);

        $criteria->setOffset(($page - 1) * $limit);
        $criteria->setLimit($limit);
        $criteria->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_EXACT);
    }

    private function handleSorting(Request $request, Criteria $criteria, SalesChannelContext $context): void
    {
        /** @var ProductSortingCollection $sortings */
        $sortings = $criteria->getExtension('sortings') ?? new ProductSortingCollection();
        $sortings->merge($this->getAvailableSortings($request, $context->getContext()));

        $currentSorting = $this->getCurrentSorting($sortings, $request);

        $criteria->addSorting(
            ...$currentSorting->createDalSorting()
        );

        $criteria->addExtension('sortings', $sortings);
    }

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

    private function getSystemDefaultSorting(SalesChannelContext $context): string
    {
        return $this->systemConfigService->getString(
            'core.listing.defaultSorting',
            $context->getSalesChannel()->getId()
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
        $result = $event->getResult();

        $filters = $result->getCriteria()->getExtension('filters');
        if (!$filters instanceof FilterCollection) {
            return;
        }

        foreach ($filters as $filter) {
            $result->addCurrentFilter($filter->getName(), $filter->getValues());
        }
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

    private function getFilters(Request $request, SalesChannelContext $context): FilterCollection
    {
        $filters = new FilterCollection();

        $filters->add($this->getManufacturerFilter($request));
        $filters->add($this->getPriceFilter($request));
        $filters->add($this->getRatingFilter($request));
        $filters->add($this->getShippingFreeFilter($request));
        $filters->add($this->getPropertyFilter($request));

        $event = new ProductListingCollectFilterEvent($request, $filters, $context);
        $this->dispatcher->dispatch($event);

        return $filters;
    }

    private function getManufacturerFilter(Request $request): Filter
    {
        $ids = $this->getManufacturerIds($request);

        return new Filter(
            'manufacturer',
            !empty($ids),
            [new EntityAggregation('manufacturer', 'product.manufacturerId', 'product_manufacturer')],
            new EqualsAnyFilter('product.manufacturerId', $ids),
            $ids
        );
    }

    private function getPropertyFilter(Request $request): Filter
    {
        $ids = $this->getPropertyIds($request);

        if (empty($ids)) {
            return new Filter(
                'properties',
                false,
                [
                    new TermsAggregation('properties', 'product.properties.id'),
                    new TermsAggregation('options', 'product.options.id'),
                ],
                new MultiFilter(MultiFilter::CONNECTION_OR, []),
                [],
                false
            );
        }

        $grouped = $this->connection->fetchAll(
            'SELECT LOWER(HEX(property_group_id)) as property_group_id, LOWER(HEX(id)) as id
             FROM property_group_option
             WHERE id IN (:ids)',
            ['ids' => Uuid::fromHexToBytesList($ids)],
            ['ids' => Connection::PARAM_STR_ARRAY]
        );

        $grouped = FetchModeHelper::group($grouped);

        $filters = [];
        foreach ($grouped as $options) {
            $options = array_column($options, 'id');

            $filters[] = new MultiFilter(
                MultiFilter::CONNECTION_OR,
                [
                    new EqualsAnyFilter('product.optionIds', $options),
                    new EqualsAnyFilter('product.propertyIds', $options),
                ]
            );
        }

        return new Filter(
            'properties',
            true,
            [
                new TermsAggregation('properties', 'product.properties.id'),
                new TermsAggregation('options', 'product.options.id'),
            ],
            new MultiFilter(MultiFilter::CONNECTION_AND, $filters),
            $ids,
            false
        );
    }

    private function getPriceFilter(Request $request): Filter
    {
        $min = $request->get('min-price', 0);
        $max = $request->get('max-price', 0);

        $range = [];
        if ($min > 0) {
            $range[RangeFilter::GTE] = $min;
        }
        if ($max > 0) {
            $range[RangeFilter::LTE] = $max;
        }

        return new Filter(
            'price',
            !empty($range),
            [new StatsAggregation('price', 'product.listingPrices', true, true, false, false)],
            new RangeFilter('product.listingPrices', $range),
            [
                'min' => $request->get('min-price'),
                'max' => $request->get('max-price'),
            ]
        );
    }

    private function getRatingFilter(Request $request): Filter
    {
        $filtered = $request->get('rating');

        return new Filter(
            'rating',
            $filtered !== null,
            [
                new FilterAggregation(
                    'rating-exists',
                    new MaxAggregation('rating', 'product.ratingAverage'),
                    [new RangeFilter('product.ratingAverage', [RangeFilter::GTE => 0])]
                ),
            ],
            new RangeFilter('product.ratingAverage', [
                RangeFilter::GTE => (int) $filtered,
            ]),
            $filtered
        );
    }

    private function getShippingFreeFilter(Request $request): Filter
    {
        $filtered = (bool) $request->get('shipping-free', false);

        return new Filter(
            'shipping-free',
            $filtered === true,
            [
                new FilterAggregation(
                    'shipping-free-filter',
                    new MaxAggregation('shipping-free', 'product.shippingFree'),
                    [new EqualsFilter('product.shippingFree', true)]
                ),
            ],
            new EqualsFilter('product.shippingFree', true),
            $filtered
        );
    }
}
