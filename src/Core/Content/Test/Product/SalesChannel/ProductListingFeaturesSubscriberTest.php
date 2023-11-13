<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Product\SalesChannel;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Events\ProductListingCriteriaEvent;
use Shopware\Core\Content\Product\Events\ProductSearchCriteriaEvent;
use Shopware\Core\Content\Product\ProductException;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingFeaturesSubscriber;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\Filter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\SingleFieldFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\TestDefaults;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
class ProductListingFeaturesSubscriberTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;

    private EventDispatcherInterface $eventDispatcher;

    /**
     * @var array<string>
     */
    private array $optionIds;

    private SystemConfigService $systemConfigService;

    private SalesChannelEntity $salesChannel;

    protected function setUp(): void
    {
        Feature::skipTestIfActive('v6.6.0.0', $this);

        parent::setUp();
        $this->eventDispatcher = $this->getContainer()->get('event_dispatcher');

        $productSortingRepository = $this->getContainer()->get('product_sorting.repository');
        $productGroupRepository = $this->getContainer()->get('property_group.repository');

        $this->optionIds = [
            'red' => Uuid::randomHex(),
            'green' => Uuid::randomHex(),
            'blue' => Uuid::randomHex(),
            'small' => Uuid::randomHex(),
            'medium' => Uuid::randomHex(),
            'large' => Uuid::randomHex(),
        ];

        $productGroupRepository->create([
            [
                'name' => 'color',
                'options' => [
                    ['id' => $this->optionIds['red'], 'name' => 'red'],
                    ['id' => $this->optionIds['green'], 'name' => 'green'],
                    ['id' => $this->optionIds['blue'], 'name' => 'blue'],
                ],
            ],
            [
                'name' => 'size',
                'options' => [
                    ['id' => $this->optionIds['small'], 'name' => 'small'],
                    ['id' => $this->optionIds['medium'], 'name' => 'medium'],
                    ['id' => $this->optionIds['large'], 'name' => 'large'],
                ],
            ],
        ], Context::createDefaultContext());

        // provide some advanced product sorting cases
        $productSortingRepository->create([
            [
                'key' => 'test-multiple-sortings',
                'priority' => 0,
                'active' => true,
                'fields' => [
                    ['field' => 'product.name', 'order' => 'asc', 'naturalSorting' => 0, 'priority' => 0],
                    ['field' => 'product.cheapestPrice', 'order' => 'desc', 'naturalSorting' => 0, 'priority' => 0],
                ],
                'label' => 'test',
            ],
            [
                'key' => 'test-inactive',
                'priority' => 0,
                'active' => false,
                'fields' => [
                    ['field' => 'product.cheapestPrice', 'order' => 'desc', 'naturalSorting' => 0, 'priority' => 0],
                ],
                'label' => 'test',
            ],
        ], Context::createDefaultContext());

        $this->systemConfigService = $this->getContainer()->get(SystemConfigService::class);

        /** @var EntityRepository<SalesChannelCollection> $salesChannelRepo */
        $salesChannelRepo = $this->getContainer()->get('sales_channel.repository');

        $salesChannel = $salesChannelRepo
            ->search(new Criteria([TestDefaults::SALES_CHANNEL]), Context::createDefaultContext())
            ->getEntities()
            ->first();

        static::assertNotNull($salesChannel);

        $this->salesChannel = $salesChannel;
    }

    /**
     * @dataProvider manufacturerProvider
     *
     * @param list<string> $expected
     */
    public function testManufacturerFilter(array $expected, Request $request): void
    {
        $criteria = new Criteria();
        $event = new ProductListingCriteriaEvent($request, $criteria, Generator::createSalesChannelContext());
        $this->eventDispatcher->dispatch($event);

        if (empty($expected)) {
            static::assertCount(0, $criteria->getPostFilters());

            return;
        }

        static::assertCount(1, $criteria->getPostFilters());
        $filter = $criteria->getPostFilters()[0];

        static::assertInstanceOf(EqualsAnyFilter::class, $filter);
        static::assertSame($expected, $filter->getValue());
    }

    /**
     * @return list<array{0: list<string>, 1: Request}>
     */
    public static function manufacturerProvider(): array
    {
        $id1 = Uuid::randomHex();
        $id2 = Uuid::randomHex();

        return [
            [[], new Request()],
            [[], new Request(['manufacturer' => ''])],
            [[$id1], new Request(['manufacturer' => $id1])],
            [[$id1, $id2], new Request(['manufacturer' => $id1 . '|' . $id2])],
        ];
    }

    /**
     * @dataProvider shippingFreeProvider
     */
    public function testShippingFreeFilter(?bool $expected, Request $request): void
    {
        $criteria = new Criteria();
        $event = new ProductListingCriteriaEvent($request, $criteria, Generator::createSalesChannelContext());
        $this->eventDispatcher->dispatch($event);

        /** @var list<SingleFieldFilter> $postFilters */
        $postFilters = $criteria->getPostFilters();
        $filters = $this->getFiltersOfField($postFilters, 'product.shippingFree');
        if ($expected === null) {
            static::assertCount(0, $filters);

            return;
        }

        static::assertCount(1, $filters);
        $filter = array_shift($filters);

        static::assertInstanceOf(EqualsFilter::class, $filter);
        static::assertSame($expected, $filter->getValue());
    }

    /**
     * @return list<array{0: ?bool, 1: Request}>
     */
    public static function shippingFreeProvider(): array
    {
        return [
            [null, new Request()],
            [true, new Request(['shipping-free' => true])],
            [null, new Request(['shipping-free' => false])],
            [null, new Request(['shipping-free' => null])],
        ];
    }

    /**
     * @dataProvider priceFilterProvider
     *
     * @param array{min?: int|null, max?: int|null} $expected
     */
    public function testPriceFilter(array $expected, Request $request): void
    {
        $criteria = new Criteria();
        $event = new ProductListingCriteriaEvent($request, $criteria, Generator::createSalesChannelContext());
        $this->eventDispatcher->dispatch($event);

        if (empty($expected)) {
            static::assertCount(0, $criteria->getPostFilters());

            return;
        }

        static::assertCount(1, $criteria->getPostFilters());
        $filter = $criteria->getPostFilters()[0];

        static::assertInstanceOf(RangeFilter::class, $filter);
        static::assertSame($expected['min'], $filter->getParameter(RangeFilter::GTE));
        static::assertSame($expected['max'], $filter->getParameter(RangeFilter::LTE));
    }

    /**
     * @return list<array{0: array{min?: int|null, max?: int|null}, 1: Request}>
     */
    public static function priceFilterProvider(): array
    {
        return [
            [['min' => 10, 'max' => null], new Request(['min-price' => 10])],
            [['min' => null, 'max' => 10], new Request(['max-price' => 10])],
            [['min' => 0, 'max' => 0], new Request(['min-price' => 0, 'max-price' => 0])],
            [['min' => 10, 'max' => 10], new Request(['min-price' => 10, 'max-price' => 10])],
            [[], new Request(['min-price' => -10, 'max-price' => -10])],
        ];
    }

    /**
     * @dataProvider listSortingProvider
     *
     * @param array<string, string> $expectedFields
     */
    public function testListSorting(array $expectedFields, Request $request): void
    {
        $criteria = new Criteria();
        $event = new ProductListingCriteriaEvent($request, $criteria, Generator::createSalesChannelContext());
        $this->eventDispatcher->dispatch($event);

        $sortings = $criteria->getSorting();

        static::assertCount(\count($expectedFields), $sortings);

        foreach ($sortings as $sorting) {
            static::assertArrayHasKey($sorting->getField(), $expectedFields);
            static::assertSame($sorting->getDirection(), $expectedFields[$sorting->getField()]);
        }
    }

    /**
     * @dataProvider searchSortingProvider
     *
     * @group slow
     *
     * @param array<string, string> $expectedFields
     */
    public function testSearchSorting(array $expectedFields, Request $request): void
    {
        $criteria = new Criteria();
        $event = new ProductSearchCriteriaEvent($request, $criteria, Generator::createSalesChannelContext());
        $this->eventDispatcher->dispatch($event);

        $sortings = $criteria->getSorting();

        static::assertCount(\count($expectedFields), $sortings);

        foreach ($sortings as $sorting) {
            static::assertArrayHasKey($sorting->getField(), $expectedFields, print_r($expectedFields, true));
            static::assertSame($sorting->getDirection(), $expectedFields[$sorting->getField()]);
        }
    }

    /**
     * @dataProvider unavailableListSortingProvider
     */
    public function testListSortingNotFound(Request $request): void
    {
        $criteria = new Criteria();
        $event = new ProductListingCriteriaEvent($request, $criteria, Generator::createSalesChannelContext());

        static::expectException(ProductException::class);

        $this->eventDispatcher->dispatch($event);
    }

    /**
     * @dataProvider unavailableSearchSortingProvider
     */
    public function testSearchSortingNotFound(Request $request): void
    {
        $criteria = new Criteria();
        $event = new ProductSearchCriteriaEvent($request, $criteria, Generator::createSalesChannelContext());

        static::expectException(ProductException::class);

        $this->eventDispatcher->dispatch($event);
    }

    /**
     * @return list<array{0: array<string, string>, 1: Request}>
     */
    public static function searchSortingProvider(): array
    {
        return [
            [
                ['product.name' => FieldSorting::ASCENDING],
                new Request(['order' => 'name-asc']),
            ],
            [
                ['product.name' => FieldSorting::DESCENDING],
                new Request(['order' => 'name-desc']),
            ],
            [
                ['product.cheapestPrice' => FieldSorting::ASCENDING],
                new Request(['order' => 'price-asc']),
            ],
            [
                ['product.cheapestPrice' => FieldSorting::DESCENDING],
                new Request(['order' => 'price-desc']),
            ],
            [
                [
                    'product.name' => FieldSorting::ASCENDING,
                    'product.cheapestPrice' => FieldSorting::DESCENDING,
                ],
                new Request(['order' => 'test-multiple-sortings']),
            ],
            [
                ['product.cheapestPrice' => FieldSorting::DESCENDING],
                new Request(['order' => 'price-desc'], ['availableSortings' => ['price-desc' => 1, 'price-asc' => 0]]),
            ],
        ];
    }

    /**
     * @return list<array{0: array<string, string>, 1: Request}>
     */
    public static function listSortingProvider(): array
    {
        return [
            [
                ['product.name' => FieldSorting::ASCENDING],
                new Request(),
            ],
            [
                ['product.name' => FieldSorting::ASCENDING],
                new Request(['order' => 'name-asc']),
            ],
            [
                ['product.name' => FieldSorting::DESCENDING],
                new Request(['order' => 'name-desc']),
            ],
            [
                ['product.cheapestPrice' => FieldSorting::ASCENDING],
                new Request(['order' => 'price-asc']),
            ],
            [
                ['product.cheapestPrice' => FieldSorting::DESCENDING],
                new Request(['order' => 'price-desc']),
            ],
            [
                [
                    'product.name' => FieldSorting::ASCENDING,
                    'product.cheapestPrice' => FieldSorting::DESCENDING,
                ],
                new Request(['order' => 'test-multiple-sortings']),
            ],
            [
                ['product.cheapestPrice' => FieldSorting::DESCENDING],
                new Request(['order' => 'price-desc'], ['availableSortings' => ['price-desc' => 1, 'price-asc' => 0]]),
            ],
        ];
    }

    /**
     * @return list<array{0: Request}>
     */
    public static function unavailableSearchSortingProvider(): array
    {
        return [
            [new Request(['order' => 'unknown'])],
            [new Request(['order' => 'test-inactive'])],
            [new Request(['order' => 'score', 'availableSortings' => ['price-desc' => 1, 'price-asc' => 0]])],
            [new Request(['order' => 'test-inactive', 'availableSortings' => ['price-desc' => 2, 'price-asc' => 1, 'test-inactive' => 0]])],
        ];
    }

    /**
     * @return list<array{0: Request}>
     */
    public static function unavailableListSortingProvider(): array
    {
        return [
            [new Request(['order' => 'unknown'])],
            [new Request(['order' => 'test-inactive'])],
            [new Request(['order' => 'name-asc', 'availableSortings' => ['price-desc' => 1, 'price-asc' => 0]])],
            [new Request(['order' => 'test-inactive', 'availableSortings' => ['price-desc' => 2, 'price-asc' => 1, 'test-inactive' => 0]])],
        ];
    }

    /**
     * @dataProvider paginationProvider
     */
    public function testPagination(int $limit, int $offset, Request $request, ?int $systemConfigLimit = null): void
    {
        if ($systemConfigLimit !== null) {
            $this->systemConfigService->set('core.listing.productsPerPage', $systemConfigLimit);
        } else {
            $this->systemConfigService->delete('core.listing.productsPerPage');
        }

        $criteria = new Criteria();
        $event = new ProductListingCriteriaEvent(
            $request,
            $criteria,
            Generator::createSalesChannelContext(null, null, null)
        );

        $this->eventDispatcher->dispatch($event);

        static::assertSame($limit, $criteria->getLimit());
        static::assertSame($offset, $criteria->getOffset());
    }

    /**
     * @dataProvider paginationSalesChannelProvider
     */
    public function testPaginationSalesChannel(int $limit, int $offset, Request $request, int $limitChannel, int $offsetChannel, ?int $systemConfigLimit = null): void
    {
        $this->systemConfigService->set('core.listing.productsPerPage', 12);

        if ($systemConfigLimit !== null) {
            $this->systemConfigService->set('core.listing.productsPerPage', $systemConfigLimit, TestDefaults::SALES_CHANNEL);
        } else {
            $this->systemConfigService->delete('core.listing.productsPerPage', TestDefaults::SALES_CHANNEL);
        }

        $criteria = new Criteria();
        $event = new ProductListingCriteriaEvent(
            $request,
            $criteria,
            Generator::createSalesChannelContext(null, null, null)
        );

        $this->eventDispatcher->dispatch($event);

        $criteriaChannel = new Criteria();
        $eventChannel = new ProductListingCriteriaEvent(
            $request,
            $criteriaChannel,
            Generator::createSalesChannelContext(null, null, $this->salesChannel)
        );

        $this->eventDispatcher->dispatch($eventChannel);

        static::assertSame($limit, $criteria->getLimit());
        static::assertSame($offset, $criteria->getOffset());
        static::assertSame($limitChannel, $criteriaChannel->getLimit());
        static::assertSame($offsetChannel, $criteriaChannel->getOffset());
    }

    /**
     * @return list<array{0: int, 1: int, 2: Request, 3?: int}>
     */
    public static function paginationProvider(): array
    {
        return [
            [24, 0, new Request()],
            [12, 0, new Request(), 12],
            [24, 0, new Request(), -5],

            [20, 80, new Request(['p' => 5, 'limit' => 20])],
            [20, 80, new Request(['p' => 5, 'limit' => 20]), 12],
            [20, 80, new Request(['p' => 5, 'limit' => 20]), -5],

            [24, 0, new Request(['p' => -5, 'limit' => -5])],
            [24, 0, new Request(['p' => -5, 'limit' => -5]), -5],
            [12, 0, new Request(['p' => -5, 'limit' => -5]), 12],

            [1, 0, new Request(['p' => 0, 'limit' => 1])],
            [1, 0, new Request(['p' => 0, 'limit' => 1]), 12],
            [1, 0, new Request(['p' => 0, 'limit' => 1]), -5],

            [20, 80, new Request([], ['p' => 5, 'limit' => 20], [], [], [], ['REQUEST_METHOD' => Request::METHOD_POST])],
            [20, 80, new Request([], ['p' => 5, 'limit' => 20], [], [], [], ['REQUEST_METHOD' => Request::METHOD_POST]), 12],
            [20, 80, new Request([], ['p' => 5, 'limit' => 20], [], [], [], ['REQUEST_METHOD' => Request::METHOD_POST]), -5],

            [24, 0, new Request([], ['p' => -5, 'limit' => -5], [], [], [], ['REQUEST_METHOD' => Request::METHOD_POST])],
            [12, 0, new Request([], ['p' => -5, 'limit' => -5], [], [], [], ['REQUEST_METHOD' => Request::METHOD_POST]), 12],
            [24, 0, new Request([], ['p' => -5, 'limit' => -5], [], [], [], ['REQUEST_METHOD' => Request::METHOD_POST]), -5],
        ];
    }

    /**
     * @return list<array{0: int, 1: int, 2: Request, 3: int}>
     */
    public static function paginationSalesChannelProvider(): array
    {
        return [
            [12, 0, new Request(), 12, 0],
            [12, 0, new Request(), 4, 0, 4],
            [12, 0, new Request(), 24, 0, -5],

            [20, 80, new Request(['p' => 5, 'limit' => 20]), 20, 80],
            [20, 80, new Request(['p' => 5, 'limit' => 20]), 20, 80, 4],
            [20, 80, new Request(['p' => 5, 'limit' => 20]), 20, 80, -5],

            [12, 0, new Request(['p' => -5, 'limit' => -5]), 12, 0],
            [12, 0, new Request(['p' => -5, 'limit' => -5]), 24, 0, -5],
            [12, 0, new Request(['p' => -5, 'limit' => -5]), 4, 0, 4],

            [1, 0, new Request(['p' => 0, 'limit' => 1]), 1, 0],
            [1, 0, new Request(['p' => 0, 'limit' => 1]), 1, 0, 4],
            [1, 0, new Request(['p' => 0, 'limit' => 1]), 1, 0, -5],

            [20, 80, new Request([], ['p' => 5, 'limit' => 20], [], [], [], ['REQUEST_METHOD' => Request::METHOD_POST]), 20, 80],
            [20, 80, new Request([], ['p' => 5, 'limit' => 20], [], [], [], ['REQUEST_METHOD' => Request::METHOD_POST]), 20, 80, 4],
            [20, 80, new Request([], ['p' => 5, 'limit' => 20], [], [], [], ['REQUEST_METHOD' => Request::METHOD_POST]), 20, 80, -5],

            [12, 0, new Request([], ['p' => -5, 'limit' => -5], [], [], [], ['REQUEST_METHOD' => Request::METHOD_POST]), 12, 0],
            [12, 0, new Request([], ['p' => -5, 'limit' => -5], [], [], [], ['REQUEST_METHOD' => Request::METHOD_POST]), 24, 0, -5],
            [12, 0, new Request([], ['p' => -5, 'limit' => -5], [], [], [], ['REQUEST_METHOD' => Request::METHOD_POST]), 4, 0, 4],
        ];
    }

    public function testPropertyFilter(): void
    {
        $cases = [
            // no filter case
            [
                [],
                new Request(),
                'Empty request creates a post filter',
            ],

            // empty filter case
            [
                [],
                new Request([
                    'properties' => '',
                ]),
                'Empty property string creates a post filter',
            ],

            // single value case
            [
                [
                    'colors' => [$this->optionIds['red']],
                ],
                new Request([
                    'properties' => $this->optionIds['red'],
                ]),
                'Can not provide a single property filter',
            ],

            // single group case
            [
                [
                    'colors' => [$this->optionIds['red'], $this->optionIds['green']],
                ],
                new Request([
                    'properties' => implode('|', [
                        $this->optionIds['red'],
                        $this->optionIds['green'],
                    ]),
                ]),
                'Can not provide multiple values for property filter',
            ],

            // split groups case
            [
                [
                    'colors' => [$this->optionIds['red'], $this->optionIds['green']],
                    'sizes' => [$this->optionIds['small'], $this->optionIds['large']],
                ],
                new Request([
                    'properties' => implode('|', [
                        $this->optionIds['red'],
                        $this->optionIds['green'],
                        $this->optionIds['small'],
                        $this->optionIds['large'],
                    ]),
                ]),
                'Can not provide multiple property group values',
            ],
        ];

        foreach ($cases as $case) {
            $this->assertPropertyFilter(...$case);
        }
    }

    /**
     * @dataProvider filterAggregationsProvider
     *
     * @param list<string> $expectedAggregations
     * @param array<string, bool|list<string>|null> $expectedRequestFilters
     */
    public function testFilterAggregations(array $expectedAggregations, array $expectedRequestFilters, Request $request): void
    {
        $criteria = new Criteria();
        $event = new ProductListingCriteriaEvent($request, $criteria, Generator::createSalesChannelContext());
        $this->eventDispatcher->dispatch($event);

        foreach ($expectedRequestFilters as $filter => $expected) {
            $default = \gettype($expected) === 'boolean' ? true : null;

            if (\is_array($expected)) {
                static::assertSame($expected, $request->request->all($filter));
            } else {
                static::assertSame($expected, $request->request->get($filter, $default));
            }
        }

        $aggregationKeys = array_keys($criteria->getAggregations());

        static::assertEquals($expectedAggregations, $aggregationKeys);
    }

    /**
     * @return list<array{0: list<string>, 1: array<string, bool|list<string>|null>, 2: Request}>
     */
    public static function filterAggregationsProvider(): array
    {
        $defaultAggregations = [
            'manufacturer',
            'price',
            'rating-exists',
            'shipping-free-filter',
            'properties',
            'options',
        ];

        $id1 = Uuid::randomHex();

        return [
            [
                $defaultAggregations,
                [
                    'manufacturer-filter' => true,
                    'price-filter' => true,
                    'rating-filter' => true,
                    'shipping-free-filter' => true,
                    'property-filter' => true,
                    ProductListingFeaturesSubscriber::PROPERTY_GROUP_IDS_REQUEST_PARAM => null,
                ],
                new Request(),
            ],
            [
                $defaultAggregations,
                [
                    'manufacturer-filter' => true,
                    'price-filter' => true,
                    'rating-filter' => true,
                    'shipping-free-filter' => true,
                    'property-filter' => true,
                    ProductListingFeaturesSubscriber::PROPERTY_GROUP_IDS_REQUEST_PARAM => null,
                ],
                new Request([], ['manufacturer-filter' => true]),
            ],
            [
                [
                    'price',
                    'rating-exists',
                    'shipping-free-filter',
                    'properties',
                    'options',
                ],
                [
                    'manufacturer-filter' => false,
                    'price-filter' => true,
                    'rating-filter' => true,
                    'shipping-free-filter' => true,
                    'property-filter' => true,
                    ProductListingFeaturesSubscriber::PROPERTY_GROUP_IDS_REQUEST_PARAM => null,
                ],
                new Request([], ['manufacturer-filter' => false]),
            ],
            [
                $defaultAggregations,
                [
                    'manufacturer-filter' => true,
                    'price-filter' => true,
                    'rating-filter' => true,
                    'shipping-free-filter' => true,
                    'property-filter' => true,
                    ProductListingFeaturesSubscriber::PROPERTY_GROUP_IDS_REQUEST_PARAM => null,
                ],
                new Request([], ['price-filter' => true]),
            ],
            [
                [
                    'manufacturer',
                    'rating-exists',
                    'shipping-free-filter',
                    'properties',
                    'options',
                ],
                [
                    'manufacturer-filter' => true,
                    'price-filter' => false,
                    'rating-filter' => true,
                    'shipping-free-filter' => true,
                    'property-filter' => true,
                    ProductListingFeaturesSubscriber::PROPERTY_GROUP_IDS_REQUEST_PARAM => null,
                ],
                new Request([], ['price-filter' => false]),
            ],
            [
                $defaultAggregations,
                [
                    'manufacturer-filter' => true,
                    'price-filter' => true,
                    'rating-filter' => true,
                    'shipping-free-filter' => true,
                    'property-filter' => true,
                    ProductListingFeaturesSubscriber::PROPERTY_GROUP_IDS_REQUEST_PARAM => null,
                ],
                new Request([], ['rating-filter' => true]),
            ],
            [
                [
                    'manufacturer',
                    'price',
                    'shipping-free-filter',
                    'properties',
                    'options',
                ],
                [
                    'manufacturer-filter' => true,
                    'price-filter' => true,
                    'rating-filter' => false,
                    'shipping-free-filter' => true,
                    'property-filter' => true,
                    ProductListingFeaturesSubscriber::PROPERTY_GROUP_IDS_REQUEST_PARAM => null,
                ],
                new Request([], ['rating-filter' => false]),
            ],
            [
                $defaultAggregations,
                [
                    'manufacturer-filter' => true,
                    'price-filter' => true,
                    'rating-filter' => true,
                    'shipping-free-filter' => true,
                    'property-filter' => true,
                    ProductListingFeaturesSubscriber::PROPERTY_GROUP_IDS_REQUEST_PARAM => null,
                ],
                new Request([], ['shipping-free-filter' => true]),
            ],
            [
                [
                    'manufacturer',
                    'price',
                    'rating-exists',
                    'properties',
                    'options',
                ],
                [
                    'manufacturer-filter' => true,
                    'price-filter' => true,
                    'rating-filter' => true,
                    'shipping-free-filter' => false,
                    'property-filter' => true,
                    ProductListingFeaturesSubscriber::PROPERTY_GROUP_IDS_REQUEST_PARAM => null,
                ],
                new Request([], ['shipping-free-filter' => false]),
            ],
            [
                $defaultAggregations,
                [
                    'manufacturer-filter' => true,
                    'price-filter' => true,
                    'rating-filter' => true,
                    'shipping-free-filter' => true,
                    'property-filter' => true,
                    ProductListingFeaturesSubscriber::PROPERTY_GROUP_IDS_REQUEST_PARAM => null,
                ],
                new Request([], ['property-filter' => true]),
            ],
            [
                [
                    'manufacturer',
                    'price',
                    'rating-exists',
                    'shipping-free-filter',
                ],
                [
                    'manufacturer-filter' => true,
                    'price-filter' => true,
                    'rating-filter' => true,
                    'shipping-free-filter' => true,
                    'property-filter' => false,
                    ProductListingFeaturesSubscriber::PROPERTY_GROUP_IDS_REQUEST_PARAM => null,
                ],
                new Request([], ['property-filter' => false]),
            ],
            [
                [
                    'manufacturer',
                    'price',
                    'rating-exists',
                    'shipping-free-filter',
                ],
                [
                    'manufacturer-filter' => true,
                    'price-filter' => true,
                    'rating-filter' => true,
                    'shipping-free-filter' => true,
                    'property-filter' => false,
                    ProductListingFeaturesSubscriber::PROPERTY_GROUP_IDS_REQUEST_PARAM => null,
                ],
                new Request([], ['property-filter' => false, ProductListingFeaturesSubscriber::PROPERTY_GROUP_IDS_REQUEST_PARAM => null]),
            ],
            [
                [
                    'manufacturer',
                    'price',
                    'rating-exists',
                    'shipping-free-filter',
                ],
                [
                    'manufacturer-filter' => true,
                    'price-filter' => true,
                    'rating-filter' => true,
                    'shipping-free-filter' => true,
                    'property-filter' => false,
                    ProductListingFeaturesSubscriber::PROPERTY_GROUP_IDS_REQUEST_PARAM => [],
                ],
                new Request([], ['property-filter' => false, ProductListingFeaturesSubscriber::PROPERTY_GROUP_IDS_REQUEST_PARAM => []]),
            ],
            [
                [
                    'manufacturer',
                    'price',
                    'rating-exists',
                    'shipping-free-filter',
                    'properties-filter',
                    'options-filter',
                ],
                [
                    'manufacturer-filter' => true,
                    'price-filter' => true,
                    'rating-filter' => true,
                    'shipping-free-filter' => true,
                    'property-filter' => false,
                    ProductListingFeaturesSubscriber::PROPERTY_GROUP_IDS_REQUEST_PARAM => [$id1],
                ],
                new Request([], ['property-filter' => false, ProductListingFeaturesSubscriber::PROPERTY_GROUP_IDS_REQUEST_PARAM => [$id1]]),
            ],
        ];
    }

    /**
     * @param array<string, list<string>> $properties
     */
    private function assertPropertyFilter(array $properties, Request $request, string $message): void
    {
        $criteria = new Criteria();
        $event = new ProductListingCriteriaEvent($request, $criteria, Generator::createSalesChannelContext());
        $this->eventDispatcher->dispatch($event);

        $filters = $criteria->getPostFilters();

        $filters = array_shift($filters);

        if (\count($properties) <= 0) {
            static::assertNull($filters);

            return;
        }

        static::assertInstanceOf(MultiFilter::class, $filters);
        static::assertCount(\count($properties), $filters->getQueries(), $message);

        $filtered = $this->getFilteredValues($filters->getQueries());

        static::assertNotEmpty($filtered, $message);

        foreach ($properties as $ids) {
            foreach ($ids as $id) {
                static::assertContains($id, $filtered, $message);
            }
        }
    }

    /**
     * @param Filter[] $filters
     *
     * @return array<mixed>
     */
    private function getFilteredValues(array $filters): array
    {
        $filtered = [];
        foreach ($filters as $filter) {
            if ($filter instanceof EqualsAnyFilter && $filter->getField() === 'product.optionIds') {
                $filtered = array_merge($filtered, $filter->getValue());
            }

            if ($filter instanceof MultiFilter) {
                $filtered = array_merge($filtered, $this->getFilteredValues($filter->getQueries()));
            }
        }

        return $filtered;
    }

    /**
     * @param list<SingleFieldFilter> $filters
     *
     * @return list<SingleFieldFilter>
     */
    private function getFiltersOfField(array $filters, string $field): array
    {
        $matches = [];
        foreach ($filters as $filter) {
            if ($filter->getField() === $field) {
                $matches[] = $filter;
            }

            if ($filter instanceof MultiFilter) {
                $matches = [...$matches, ...$this->getFiltersOfField($filter->getQueries(), $field)];
            }
        }

        return $matches;
    }
}
