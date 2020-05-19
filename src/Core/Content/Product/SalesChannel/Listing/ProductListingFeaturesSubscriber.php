<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\Listing;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Product\Events\ProductListingCriteriaEvent;
use Shopware\Core\Content\Product\Events\ProductListingResultEvent;
use Shopware\Core\Content\Product\Events\ProductSearchCriteriaEvent;
use Shopware\Core\Content\Product\Events\ProductSearchResultEvent;
use Shopware\Core\Content\Product\Events\ProductSuggestCriteriaEvent;
use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\FetchModeHelper;
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
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;

class ProductListingFeaturesSubscriber implements EventSubscriberInterface
{
    public const DEFAULT_SORT = 'name-asc';
    public const DEFAULT_SEARCH_SORT = 'score';

    /**
     * @var EntityRepositoryInterface
     */
    private $optionRepository;

    /**
     * @var ProductListingSortingRegistry
     */
    private $sortingRegistry;

    /**
     * @var Connection
     */
    private $connection;

    public function __construct(
        Connection $connection,
        EntityRepositoryInterface $optionRepository,
        ProductListingSortingRegistry $sortingRegistry
    ) {
        $this->optionRepository = $optionRepository;
        $this->connection = $connection;
        $this->sortingRegistry = $sortingRegistry;
    }

    public static function getSubscribedEvents()
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
            ProductListingResultEvent::class => 'handleResult',
            ProductSearchResultEvent::class => 'handleResult',
        ];
    }

    public function switchFilter(ProductListingCriteriaEvent $event): void
    {
        $request = $event->getRequest();
        $criteria = $event->getCriteria();

        if ($request->get('no-aggregations') === true) {
            $criteria->resetAggregations();
        }

        // switch all post filters to normal filters to reduce remaining aggregations
        if ($request->get('reduce-aggregations')) {
            foreach ($criteria->getPostFilters() as $filter) {
                $criteria->addFilter($filter);
            }
            $criteria->resetPostFilters();
        }

        if ($request->get('only-aggregations') === true) {
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

        $criteria->addAssociation('cover.media');

        $this->handlePagination($request, $criteria);
        $this->handleFilters($request, $criteria);
        $this->handleSorting($request, $criteria, self::DEFAULT_SORT);
    }

    public function handleSearchRequest(ProductSearchCriteriaEvent $event): void
    {
        $request = $event->getRequest();
        $criteria = $event->getCriteria();

        $criteria->addAssociation('cover.media');

        $this->handlePagination($request, $criteria);
        $this->handleFilters($request, $criteria);
        $this->handleSorting($request, $criteria, self::DEFAULT_SEARCH_SORT);
    }

    public function handleResult(ProductListingResultEvent $event): void
    {
        $this->groupOptionAggregations($event);

        $this->addCurrentFilters($event);

        $defaultSort = $event instanceof ProductSearchResultEvent ? self::DEFAULT_SEARCH_SORT : self::DEFAULT_SORT;
        $currentSorting = $this->getCurrentSorting($event->getRequest(), $defaultSort);

        $event->getResult()->setSorting($currentSorting);

        $sortings = $this->sortingRegistry->getSortings();
        /** @var ProductListingSorting $sorting */
        foreach ($sortings as $sorting) {
            $sorting->setActive($sorting->getKey() === $currentSorting);
        }

        if (!$event instanceof ProductSearchResultEvent) {
            unset($sortings['score']);
        }

        $event->getResult()->setSortings($sortings);

        $event->getResult()->setPage($this->getPage($event->getRequest()));

        $event->getResult()->setLimit($this->getLimit($event->getRequest()));
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
        $criteria->addAggregation(
            new TermsAggregation('properties', 'product.properties.id')
        );
        $criteria->addAggregation(
            new TermsAggregation('options', 'product.options.id')
        );

        $ids = $this->getPropertyIds($request);

        if (empty($ids)) {
            return;
        }

        $grouped = $this->connection->fetchAll(
            'SELECT LOWER(HEX(property_group_id)) as property_group_id, LOWER(HEX(id)) as id FROM property_group_option WHERE id IN (:ids)',
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
            new StatsAggregation('price', 'product.listingPrices')
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

    private function handleSorting(Request $request, Criteria $criteria, string $defaultSorting): void
    {
        $currentSorting = $this->getCurrentSorting($request, $defaultSorting);

        if (!$currentSorting) {
            return;
        }

        $sorting = $this->sortingRegistry->get(
            $currentSorting
        );

        if (!$sorting) {
            return;
        }

        foreach ($sorting->createDalSortings() as $fieldSorting) {
            $criteria->addSorting($fieldSorting);
        }
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

    private function groupOptionAggregations(ProductListingResultEvent $event): void
    {
        $ids = $this->collectOptionIds($event);

        if (empty($ids)) {
            return;
        }

        $criteria = new Criteria($ids);
        $criteria->addAssociation('group');
        $criteria->addAssociation('media');

        $result = $this->optionRepository->search($criteria, $event->getContext());

        /** @var PropertyGroupOptionCollection $options */
        $options = $result->getEntities();

        // group options by their property-group
        $grouped = $options->groupByPropertyGroups();
        $grouped->sortByPositions();

        // remove id results to prevent wrong usages
        $event->getResult()->getAggregations()->remove('properties');
        $event->getResult()->getAggregations()->remove('configurators');
        $event->getResult()->getAggregations()->remove('options');
        $event->getResult()->getAggregations()->add(new EntityResult('properties', $grouped));
    }

    private function addCurrentFilters(ProductListingResultEvent $event): void
    {
        $event->getResult()->addCurrentFilter('manufacturer', $this->getManufacturerIds($event->getRequest()));

        $event->getResult()->addCurrentFilter('properties', $this->getPropertyIds($event->getRequest()));

        $event->getResult()->addCurrentFilter('shipping-free', $event->getRequest()->get('shipping-free'));

        $event->getResult()->addCurrentFilter('rating', $event->getRequest()->get('rating'));

        $event->getResult()->addCurrentFilter('price', [
            'min' => $event->getRequest()->get('min-price'),
            'max' => $event->getRequest()->get('max-price'),
        ]);
    }

    private function getCurrentSorting(Request $request, string $default): ?string
    {
        // @deprecated tag:v6.3.0 - use `order` instead
        $sort = $request->get('sort', null);

        if (is_string($sort) && !empty($sort)) {
            $request->query->set('order', $sort);
            $request->request->set('order', $sort);

            $request->query->set('sort', null);
            $request->request->set('sort', null);
        }

        $key = $request->get('order', $default);

        if (!$key) {
            return null;
        }

        if ($this->sortingRegistry->has($key)) {
            return $key;
        }

        return $default;
    }

    private function getManufacturerIds(Request $request): array
    {
        $ids = $request->query->get('manufacturer', '');
        if ($request->isMethod(Request::METHOD_POST)) {
            $ids = $request->request->get('manufacturer', '');
        }

        if (is_string($ids)) {
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

        if (is_string($ids)) {
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
