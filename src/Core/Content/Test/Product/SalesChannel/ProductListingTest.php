<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Product\SalesChannel;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingRoute;
use Shopware\Core\Content\Property\PropertyGroupCollection;
use Shopware\Core\Content\Property\PropertyGroupEntity;
use Shopware\Core\Content\Test\Product\SalesChannel\Fixture\ListingTestData;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\EntityResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelFunctionalTestBehaviour;
use Shopware\Core\Framework\Test\TestDataCollection;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 *
 * @group slow
 */
class ProductListingTest extends TestCase
{
    use SalesChannelFunctionalTestBehaviour;

    private string $categoryId;

    private ListingTestData $testData;

    private string $categoryStreamId;

    private Context $context;

    private string $productIdWidth100;

    private string $productIdWidth150;

    private string $salesChannelId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->context = Context::createDefaultContext();

        $salesChannel = $this->createSalesChannel();
        $this->salesChannelId = $salesChannel['id'];

        $parent = $salesChannel['navigationCategoryId'];

        $this->categoryId = Uuid::randomHex();

        $this->getContainer()->get('category.repository')
            ->create([['id' => $this->categoryId, 'name' => 'test', 'parentId' => $parent]], Context::createDefaultContext());

        $this->testData = new ListingTestData();

        $this->insertOptions();

        $this->insertProducts();

        $this->categoryStreamId = Uuid::randomHex();
    }

    public function testListing(): void
    {
        $request = new Request();

        $context = $this->getContainer()->get(SalesChannelContextFactory::class)
            ->create(Uuid::randomHex(), $this->salesChannelId);

        $listing = $this->getContainer()
            ->get(ProductListingRoute::class)
            ->load($this->categoryId, $request, $context, new Criteria())
            ->getResult();

        static::assertSame(10, $listing->getTotal());
        static::assertFalse($listing->has($this->testData->getId('product1')));

        self::assertVariationsInListing($listing, [
            $this->testData->getId('product1-red-l-steel'),
            $this->testData->getId('product1-red-xl-steel'),
            $this->testData->getId('product1-red-l-iron'),
            $this->testData->getId('product1-red-xl-iron'),
        ]);
        self::assertVariationsInListing($listing, [
            $this->testData->getId('product1-green-l-steel'),
            $this->testData->getId('product1-green-xl-steel'),
            $this->testData->getId('product1-green-l-iron'),
            $this->testData->getId('product1-green-xl-iron'),
        ]);

        // product 2 should display only the both color variants
        static::assertFalse($listing->has($this->testData->getId('product2')));
        static::assertTrue($listing->has($this->testData->getId('product2-green')));
        static::assertTrue($listing->has($this->testData->getId('product2-red')));

        // product 3 has no variants
        static::assertTrue($listing->has($this->testData->getId('product3')));

        self::assertVariationsInListing($listing, [
            $this->testData->getId('product4-red-l-iron'),
            $this->testData->getId('product4-red-xl-iron'),
        ]);
        self::assertVariationsInListing($listing, [
            $this->testData->getId('product4-red-l-steel'),
            $this->testData->getId('product4-red-xl-steel'),
        ]);
        self::assertVariationsInListing($listing, [
            $this->testData->getId('product4-green-l-iron'),
            $this->testData->getId('product4-green-xl-iron'),
        ]);
        self::assertVariationsInListing($listing, [
            $this->testData->getId('product4-green-l-steel'),
            $this->testData->getId('product4-green-xl-steel'),
        ]);

        self::assertVariationsInListing($listing, [
            $this->testData->getId('product5-red'),
            $this->testData->getId('product5-green'),
        ]);

        /** @var EntityResult $result */
        $result = $listing->getAggregations()->get('properties');

        /** @var PropertyGroupCollection $options */
        $options = $result->getEntities();
        $ids = array_keys($options->getOptionIdMap());

        static::assertContains($this->testData->getId('green'), $ids);
        static::assertContains($this->testData->getId('red'), $ids);
        static::assertContains($this->testData->getId('xl'), $ids);
        static::assertContains($this->testData->getId('l'), $ids);
        static::assertContains($this->testData->getId('iron'), $ids);
        static::assertContains($this->testData->getId('steel'), $ids);
        static::assertFalse($options->has($this->testData->getId('yellow')));
        static::assertFalse($options->has($this->testData->getId('cotton')));
    }

    /**
     * @group slow
     */
    public function testListingWithProductStream(): void
    {
        $this->createTestProductStreamEntity($this->categoryStreamId);
        $request = new Request();

        $context = $this->getContainer()->get(SalesChannelContextFactory::class)
            ->create(Uuid::randomHex(), $this->salesChannelId);

        $listing = $this->getContainer()
            ->get(ProductListingRoute::class)
            ->load($this->categoryStreamId, $request, $context, new Criteria())
            ->getResult();

        static::assertSame(7, $listing->getTotal());
        static::assertFalse($listing->has($this->productIdWidth100));
        static::assertTrue($listing->has($this->productIdWidth150));
    }

    public function testListingWithProductStreamAndAdditionalCriteria(): void
    {
        $this->createTestProductStreamEntity($this->categoryStreamId);
        $request = new Request();

        $context = $this->getContainer()->get(SalesChannelContextFactory::class)
            ->create(Uuid::randomHex(), $this->salesChannelId);

        $criteria = new Criteria();
        $criteria->addFilter(new ContainsFilter('name', 'Foo Bar'));

        $listing = $this->getContainer()
            ->get(ProductListingRoute::class)
            ->load($this->categoryStreamId, $request, $context, $criteria)
            ->getResult();

        static::assertSame(3, $listing->getTotal());
        $firstFilter = $listing->getCriteria()->getFilters()[0];
        static::assertInstanceOf(ContainsFilter::class, $firstFilter);
        static::assertEquals('name', $firstFilter->getField());
        static::assertEquals('Foo Bar', $firstFilter->getValue());
    }

    public function testNotFilterableProperty(): void
    {
        $request = new Request();

        $context = $this->getContainer()->get(SalesChannelContextFactory::class)
            ->create(Uuid::randomHex(), $this->salesChannelId);

        $request->attributes->set('_route_params', [
            'navigationId' => $this->categoryId,
        ]);

        $listing = $this->getContainer()
            ->get(ProductListingRoute::class)
            ->load($this->categoryId, $request, $context, new Criteria())
            ->getResult();

        /** @var EntityResult $result */
        $result = $listing->getAggregations()->get('properties');

        $propertyGroups = $result->getEntities();
        $propertyGroupIds = [];

        /** @var PropertyGroupEntity $propertyGroup */
        foreach ($propertyGroups as $propertyGroup) {
            $propertyGroupIds[] = $propertyGroup->getId();
        }

        static::assertContains($this->testData->getId('color'), $propertyGroupIds);
        static::assertContains($this->testData->getId('size'), $propertyGroupIds);
        static::assertContains($this->testData->getId('material'), $propertyGroupIds);
        static::assertNotContains($this->testData->getId('class'), $propertyGroupIds);
    }

    /**
     * Small helper function which asserts the one of the provided pool ids are in the result set but the remaining ids are excluded.
     *
     * @param array<string> $pool
     */
    private static function assertVariationsInListing(EntitySearchResult $result, array $pool): void
    {
        $match = null;
        // find matching id
        foreach ($pool as $index => $id) {
            if ($result->has($id)) {
                $match = $id;
                unset($pool[$index]);

                break;
            }
        }
        // assert that one id found
        static::assertNotNull($match);

        // after one id found, assert that all other ids are not inside the result set
        foreach ($pool as $id) {
            static::assertFalse($result->has($id));
        }
    }

    private function insertProducts(): void
    {
        $this->createProduct(
            'product1',
            [
                [$this->testData->getId('red'), $this->testData->getId('green')],
                [$this->testData->getId('xl'), $this->testData->getId('l')],
                [$this->testData->getId('iron'), $this->testData->getId('steel')],
            ],
            [$this->testData->getId('color')]
        );

        $this->createProduct(
            'product2',
            [
                [$this->testData->getId('red'), $this->testData->getId('green')],
            ],
            [$this->testData->getId('color')]
        );

        $this->createProduct('product3', [], []);

        $this->createProduct(
            'product4',
            [
                [$this->testData->getId('red'), $this->testData->getId('green')],
                [$this->testData->getId('xl'), $this->testData->getId('l')],
                [$this->testData->getId('iron'), $this->testData->getId('steel')],
            ],
            [$this->testData->getId('color'), $this->testData->getId('material')]
        );

        $this->createProduct(
            'product5',
            [
                [$this->testData->getId('red'), $this->testData->getId('green')],
            ],
            []
        );
    }

    /**
     * @param array<array<string>> $options
     * @param array<string> $listingGroups
     */
    private function createProduct(string $key, array $options, array $listingGroups): void
    {
        $config = [];
        foreach ($listingGroups as $groupId) {
            $config[] = [
                'id' => $groupId,
                'expressionForListings' => true,
                'representation' => 'box', // box, select, image, color
            ];
        }

        $configurator = [];
        foreach ($options as $grouped) {
            foreach ($grouped as $optionId) {
                $configurator[] = ['optionId' => $optionId];
            }
        }

        $id = $this->testData->createId($key);
        $data = [
            [
                'id' => $id,
                'variantListingConfig' => [
                    'configuratorGroupConfig' => $config,
                ],
                'productNumber' => $id,
                'manufacturer' => ['name' => 'test'],
                'tax' => ['taxRate' => 19, 'name' => 'test'],
                'stock' => 10,
                'name' => $key,
                'active' => true,
                'price' => [
                    ['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => true],
                ],
                'categories' => [
                    ['id' => $this->categoryId],
                ],
                'configuratorSettings' => $configurator,
                'visibilities' => [
                    [
                        'salesChannelId' => $this->salesChannelId,
                        'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL,
                    ],
                ],
            ],
        ];

        if (!empty($options)) {
            foreach ($this->combos($options) as $index => $combination) {
                $variantKey = $key . '-' . implode('-', $this->testData->getKeyList($combination));

                $data[] = [
                    'id' => $this->testData->createId($variantKey),
                    'productNumber' => $key . '.' . $index,
                    'stock' => 10,
                    'name' => $variantKey,
                    'active' => true,
                    'parentId' => $this->testData->getId($key),
                    'options' => array_map(static fn ($id) => ['id' => $id], $combination),
                ];
            }
        }

        $repo = $this->getContainer()->get('product.repository');

        $repo->create($data, Context::createDefaultContext());
    }

    /**
     * Rec. Function to find all possible combinations of $data input
     *
     * @param array<array<string>> $data
     * @param array<array<string>>$all
     * @param array<string> $group
     *
     * @return array<array<string>>
     */
    private function combos(array $data, &$all = [], $group = [], ?string $val = null, int $i = 0): array
    {
        if (isset($val)) {
            $group[] = $val;
        }
        if ($i >= \count($data)) {
            $all[] = $group;
        } else {
            foreach ($data[$i] as $v) {
                $this->combos($data, $all, $group, $v, $i + 1);
            }
        }

        return $all;
    }

    private function insertOptions(): void
    {
        $data = [
            [
                'id' => $this->testData->createId('color'),
                'name' => 'color',
                'options' => [
                    ['id' => $this->testData->createId('green'), 'name' => 'green'],
                    ['id' => $this->testData->createId('red'), 'name' => 'red'],
                    ['id' => $this->testData->createId('yellow'), 'name' => 'red'],
                ],
            ],
            [
                'id' => $this->testData->createId('size'),
                'name' => 'size',
                'options' => [
                    ['id' => $this->testData->createId('xl'), 'name' => 'XL'],
                    ['id' => $this->testData->createId('l'), 'name' => 'L'],
                ],
            ],
            [
                'id' => $this->testData->createId('material'),
                'name' => 'material',
                'options' => [
                    ['id' => $this->testData->createId('iron'), 'name' => 'iron'],
                    ['id' => $this->testData->createId('steel'), 'name' => 'steel'],
                    ['id' => $this->testData->createId('cotton'), 'name' => 'steel'],
                ],
            ],
            [
                'id' => $this->testData->createId('class'),
                'name' => 'class',
                'options' => [
                    ['id' => $this->testData->createId('first'), 'name' => 'first'],
                    ['id' => $this->testData->createId('business'), 'name' => 'business'],
                    ['id' => $this->testData->createId('coach'), 'name' => 'coach'],
                ],
            ],
        ];

        /** @var EntityRepository $repo */
        $repo = $this->getContainer()->get('property_group.repository');
        $repo->create($data, Context::createDefaultContext());
    }

    private function createTestProductStreamEntity(string $categoryStreamId): void
    {
        $streamId = Uuid::randomHex();

        $randomProductIds = implode('|', array_column($this->createProducts(), 'id'));

        $stream = [
            'id' => $streamId,
            'name' => 'testStream',
            'filters' => [
                [
                    'type' => 'multi',
                    'queries' => [
                        [
                            'type' => 'equalsAny',
                            'field' => 'product.id',
                            'value' => $randomProductIds,
                        ],
                        [
                            'type' => 'range',
                            'field' => 'product.width',
                            'parameters' => [
                                'gte' => 120,
                                'lte' => 180,
                            ],
                        ],
                    ],
                    'operator' => 'AND',
                ],
            ],
        ];
        $productRepository = $this->getContainer()->get('product_stream.repository');
        $productRepository->create([$stream], $this->context);

        $this->getContainer()->get('category.repository')
            ->create([['id' => $categoryStreamId, 'productStreamId' => $streamId, 'name' => 'test', 'parentId' => null, 'productAssignmentType' => 'product_stream']], Context::createDefaultContext());
    }

    /**
     * @return array<array{id: string, productNumber: string, width: string, stock: int, name: string}>
     */
    private function createProducts(): array
    {
        $ids = new TestDataCollection();
        $ids->create('manufacturer');
        $ids->create('taxId');

        $productRepository = $this->getContainer()->get('product.repository');
        $salesChannelId = $this->salesChannelId;
        $products = [];

        $widths = [
            '100',
            '110',
            '120',
            '130',
            '140',
            '150',
            '160',
            '170',
            '180',
            '190',
        ];

        $names = [
            'Wooden Heavy Magma',
            'Small Plastic Prawn Leather',
            'Fantastic Marble Megahurts',
            'Foo Bar Aerodynamic Iron Viagreat',
            'Foo Bar Awesome Bronze Sulpha Quik',
            'Foo Bar Aerodynamic Silk Ideoswitch',
            'Heavy Duty Wooden Magnina',
            'Incredible Wool Q-lean',
            'Heavy Duty Cotton Gristle Chips',
            'Heavy Steel Hot Magma',
        ];

        for ($i = 0; $i < 10; ++$i) {
            $products[] = [
                'id' => Uuid::randomHex(),
                'productNumber' => Uuid::randomHex(),
                'width' => $widths[$i],
                'stock' => 1,
                'name' => $names[$i],
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
                'manufacturer' => ['id' => $ids->get('manufacturer'), 'name' => 'test'],
                'tax' => ['id' => $ids->get('taxId'), 'taxRate' => 17, 'name' => 'with id'],
                'visibilities' => [
                    ['salesChannelId' => $salesChannelId, 'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL],
                ],
            ];
        }

        $this->productIdWidth100 = $products[0]['id'];
        $this->productIdWidth150 = $products[5]['id'];

        $productRepository->create($products, $this->context);

        return $products;
    }
}
