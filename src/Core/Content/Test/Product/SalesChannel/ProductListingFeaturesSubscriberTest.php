<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Product\SalesChannel;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Test\Cart\Common\Generator;
use Shopware\Core\Content\Product\Events\ProductListingCriteriaEvent;
use Shopware\Core\Content\Product\Events\ProductSearchCriteriaEvent;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;

class ProductListingFeaturesSubscriberTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var EventDispatcher
     */
    private $eventDispatcher;

    /**
     * @var string[]
     */
    private $optionIds;

    protected function setUp(): void
    {
        parent::setUp();
        $this->eventDispatcher = $this->getContainer()->get('event_dispatcher');

        $repo = $this->getContainer()->get('property_group.repository');

        $this->optionIds = [
            'red' => Uuid::randomHex(),
            'green' => Uuid::randomHex(),
            'blue' => Uuid::randomHex(),
            'small' => Uuid::randomHex(),
            'medium' => Uuid::randomHex(),
            'large' => Uuid::randomHex(),
        ];

        /* @var EntityRepositoryInterface $repo */
        $repo->create([
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
    }

    /**
     * @dataProvider manufacturerProvider
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

    public function manufacturerProvider(): array
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

        if ($expected === null) {
            static::assertCount(0, $criteria->getPostFilters());

            return;
        }

        static::assertCount(1, $criteria->getPostFilters());
        $filter = $criteria->getPostFilters()[0];

        static::assertInstanceOf(EqualsFilter::class, $filter);
        static::assertSame($expected, $filter->getValue());
    }

    public function shippingFreeProvider()
    {
        return [
            [null, new Request()],
            [true, new Request(['shipping-free' => true])],
            [null, new Request(['shipping-free' => false])],
            [null, new Request(['shipping-free' => null])],
        ];
    }

    /**
     * @dataProvider listSortingProvider
     */
    public function testListSorting(array $expectedFields, Request $request): void
    {
        $criteria = new Criteria();
        $event = new ProductListingCriteriaEvent($request, $criteria, Generator::createSalesChannelContext());
        $this->eventDispatcher->dispatch($event);

        $sortings = $criteria->getSorting();
        static::assertCount(count($expectedFields), $sortings);

        foreach ($sortings as $sorting) {
            static::assertArrayHasKey($sorting->getField(), $expectedFields);
            static::assertSame($sorting->getDirection(), $expectedFields[$sorting->getField()]);
        }
    }

    /**
     * @dataProvider searchSortingProvider
     */
    public function testSearchSorting(array $expectedFields, Request $request): void
    {
        $criteria = new Criteria();
        $event = new ProductSearchCriteriaEvent($request, $criteria, Generator::createSalesChannelContext());
        $this->eventDispatcher->dispatch($event);

        $sortings = $criteria->getSorting();
        static::assertCount(count($expectedFields), $sortings, print_r($sortings, true));

        foreach ($sortings as $sorting) {
            static::assertArrayHasKey($sorting->getField(), $expectedFields);
            static::assertSame($sorting->getDirection(), $expectedFields[$sorting->getField()]);
        }
    }

    public function searchSortingProvider(): array
    {
        return [
            [
                ['_score' => FieldSorting::DESCENDING],
                new Request(),
            ],
            [
                ['product.name' => FieldSorting::ASCENDING],
                new Request(['sort' => 'name-asc']),
            ],
            [
                ['_score' => FieldSorting::DESCENDING],
                new Request(['sort' => 'unknown']),
            ],
            [
                ['product.name' => FieldSorting::DESCENDING],
                new Request(['sort' => 'name-desc']),
            ],
            [
                ['product.listingPrices' => FieldSorting::ASCENDING],
                new Request(['sort' => 'price-asc']),
            ],
            [
                ['product.listingPrices' => FieldSorting::DESCENDING],
                new Request(['sort' => 'price-desc']),
            ],
        ];
    }

    public function listSortingProvider(): array
    {
        return [
            [
                ['product.name' => FieldSorting::ASCENDING],
                new Request(),
            ],
            [
                ['product.name' => FieldSorting::ASCENDING],
                new Request(['sort' => 'name-asc']),
            ],
            [
                ['product.name' => FieldSorting::ASCENDING],
                new Request(['sort' => 'unknown']),
            ],
            [
                ['product.name' => FieldSorting::DESCENDING],
                new Request(['sort' => 'name-desc']),
            ],
            [
                ['product.listingPrices' => FieldSorting::ASCENDING],
                new Request(['sort' => 'price-asc']),
            ],
            [
                ['product.listingPrices' => FieldSorting::DESCENDING],
                new Request(['sort' => 'price-desc']),
            ],
        ];
    }

    /**
     * @dataProvider paginationProvider
     */
    public function testPagination(int $limit, int $offset, Request $request): void
    {
        $criteria = new Criteria();
        $event = new ProductListingCriteriaEvent($request, $criteria, Generator::createSalesChannelContext());
        $this->eventDispatcher->dispatch($event);

        static::assertSame($limit, $criteria->getLimit());
        static::assertSame($offset, $criteria->getOffset());
    }

    public function paginationProvider()
    {
        return [
            [24, 0, new Request()],
            [20, 80, new Request(['p' => 5, 'limit' => 20])],
            [1, 0, new Request(['p' => 0, 'limit' => 1])],
            [24, 0, new Request(['p' => -5, 'limit' => -5])],
            [20, 80, new Request([], ['p' => 5, 'limit' => 20], [], [], [], ['REQUEST_METHOD' => Request::METHOD_POST])],
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

    private function assertPropertyFilter(array $properties, Request $request, string $message): void
    {
        $criteria = new Criteria();
        $event = new ProductListingCriteriaEvent($request, $criteria, Generator::createSalesChannelContext());
        $this->eventDispatcher->dispatch($event);

        $filters = $criteria->getPostFilters();

        static::assertCount(\count($properties), $filters, $message);

        $filtered = [];

        foreach ($filters as $filter) {
            if (!$filter instanceof MultiFilter) {
                continue;
            }

            foreach ($filter->getQueries() as $query) {
                if (!$query instanceof EqualsAnyFilter) {
                    continue;
                }

                if ($query->getField() !== 'product.optionIds') {
                    continue;
                }

                foreach ($query->getValue() as $id) {
                    $filtered[] = $id;
                }
            }
        }

        if (empty($properties)) {
            return;
        }

        static::assertNotEmpty($filtered, $message);

        foreach ($properties as $ids) {
            foreach ($ids as $id) {
                static::assertContains($id, $filtered, $message);
            }
        }
    }
}
