<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Product\SalesChannel\CrossSelling;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductCrossSelling\ProductCrossSellingDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\Events\ProductCrossSellingIdsCriteriaEvent;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Product\SalesChannel\AbstractProductCloseoutFilterFactory;
use Shopware\Core\Content\Product\SalesChannel\CrossSelling\AbstractProductCrossSellingRoute;
use Shopware\Core\Content\Product\SalesChannel\CrossSelling\ProductCrossSellingRoute;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingLoader;
use Shopware\Core\Content\ProductStream\Service\ProductStreamBuilderInterface;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\TaxAddToSalesChannelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\TestDefaults;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Group('slow')]
#[Group('store-api')]
class CrossSellingRouteTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;
    use TaxAddToSalesChannelTestBehaviour;

    private SalesChannelContext $salesChannelContext;

    private EntityRepository $productRepository;

    private AbstractProductCrossSellingRoute $route;

    private KernelBrowser $browser;

    protected function setUp(): void
    {
        $this->salesChannelContext = Generator::createSalesChannelContext(
            null,
            null,
            (new SalesChannelEntity())->assign([
                'id' => TestDefaults::SALES_CHANNEL,
                'taxCalculationType' => SalesChannelDefinition::CALCULATION_TYPE_VERTICAL,
            ])
        );
        $this->productRepository = $this->getContainer()->get('product.repository');
        $this->route = $this->getContainer()->get(ProductCrossSellingRoute::class);

        $this->browser = $this->createCustomSalesChannelBrowser([
            'id' => TestDefaults::SALES_CHANNEL,
            'languages' => [],
        ]);
    }

    public function testLoad(): void
    {
        $productId = Uuid::randomHex();

        $productData = $this->getProductData($productId);
        $productData['crossSellings'] = [[
            'name' => 'Test Cross Selling',
            'sortBy' => ProductCrossSellingDefinition::SORT_BY_PRICE,
            'sortDirection' => FieldSorting::ASCENDING,
            'active' => true,
            'limit' => 3,
            'productStreamId' => $this->createProductStream(),
        ]];

        $this->productRepository->create([$productData], $this->salesChannelContext->getContext());

        $result = $this->route->load($productId, new Request(), $this->salesChannelContext, new Criteria())
            ->getResult();

        static::assertEquals(1, $result->count());

        $element = $result->first();
        static::assertNotNull($element);
        static::assertEquals(3, $element->getTotal());
        static::assertEquals('Test Cross Selling', $element->getCrossSelling()->getName());

        $lastPrice = 0;
        foreach ($element->getProducts() as $product) {
            $productPrice = $product->getCurrencyPrice(Defaults::CURRENCY);
            static::assertNotNull($productPrice);
            static::assertGreaterThanOrEqual($lastPrice, $productPrice->getGross());
            $lastPrice = $productPrice->getGross();
        }
    }

    public function testLoadForProduct(): void
    {
        $productId = Uuid::randomHex();

        $productData = $this->getProductData($productId);
        $productData['crossSellings'] = [[
            'name' => 'Test Cross Selling',
            'sortBy' => ProductCrossSellingDefinition::SORT_BY_PRICE,
            'sortDirection' => FieldSorting::ASCENDING,
            'active' => true,
            'limit' => 3,
            'productStreamId' => $this->createProductStream(),
        ]];

        $this->productRepository->create([$productData], $this->salesChannelContext->getContext());

        $product = $this->productRepository->search(new Criteria([$productId]), $this->salesChannelContext->getContext())->get($productId);
        static::assertInstanceOf(ProductEntity::class, $product);
        $result = $this->route->load($product->getId(), new Request(), $this->salesChannelContext, new Criteria())->getResult();

        static::assertEquals(1, $result->count());

        $element = $result->first();
        static::assertNotNull($element);
        static::assertEquals(3, $element->getTotal());
        static::assertEquals('Test Cross Selling', $element->getCrossSelling()->getName());

        $lastPrice = 0;
        foreach ($element->getProducts() as $product) {
            $productPrice = $product->getCurrencyPrice(Defaults::CURRENCY);
            static::assertNotNull($productPrice);
            static::assertGreaterThanOrEqual($lastPrice, $productPrice->getGross());
            $lastPrice = $productPrice->getGross();
        }
    }

    public function testLoadForProductWithCloseoutAndFilterDisabled(): void
    {
        // disable hideCloseoutProductsWhenOutOfStock filter
        $this->getContainer()->get(SystemConfigService::class)
            ->set('core.listing.hideCloseoutProductsWhenOutOfStock', false);

        $productId = Uuid::randomHex();

        $productData = $this->getProductData($productId);
        $productData['crossSellings'] = [[
            'name' => 'Test Cross Selling',
            'sortBy' => ProductCrossSellingDefinition::SORT_BY_PRICE,
            'sortDirection' => FieldSorting::ASCENDING,
            'active' => true,
            'limit' => 3,
            'productStreamId' => $this->createProductStream(true),
        ]];

        $this->productRepository->create([$productData], $this->salesChannelContext->getContext());

        $product = $this->productRepository->search(new Criteria([$productId]), $this->salesChannelContext->getContext())->get($productId);
        static::assertInstanceOf(ProductEntity::class, $product);
        $result = $this->route->load($product->getId(), new Request(), $this->salesChannelContext, new Criteria())->getResult();

        static::assertEquals(1, $result->count());

        $element = $result->first();
        static::assertNotNull($element);
        static::assertEquals(3, $element->getTotal());
        static::assertEquals('Test Cross Selling', $element->getCrossSelling()->getName());

        $lastPrice = 0;
        foreach ($element->getProducts() as $product) {
            $productPrice = $product->getCurrencyPrice(Defaults::CURRENCY);
            static::assertNotNull($productPrice);
            static::assertGreaterThanOrEqual($lastPrice, $productPrice->getGross());
            $lastPrice = $productPrice->getGross();
        }
    }

    public function testLoadForProductWithCloseoutAndFilterEnabled(): void
    {
        // enable hideCloseoutProductsWhenOutOfStock filter
        $this->getContainer()->get(SystemConfigService::class)
            ->set('core.listing.hideCloseoutProductsWhenOutOfStock', true);

        $productId = Uuid::randomHex();

        $productData = $this->getProductData($productId);
        $productData['crossSellings'] = [[
            'name' => 'Test Cross Selling',
            'sortBy' => ProductCrossSellingDefinition::SORT_BY_PRICE,
            'sortDirection' => FieldSorting::ASCENDING,
            'active' => true,
            'limit' => 3,
            'productStreamId' => $this->createProductStream(true),
        ]];

        $this->productRepository->create([$productData], $this->salesChannelContext->getContext());

        $product = $this->productRepository->search(new Criteria([$productId]), $this->salesChannelContext->getContext())->get($productId);
        static::assertInstanceOf(ProductEntity::class, $product);
        $result = $this->route->load($product->getId(), new Request(), $this->salesChannelContext, new Criteria())->getResult();

        static::assertEquals(1, $result->count());

        $element = $result->first();
        static::assertNotNull($element);
        static::assertEquals(1, $element->getTotal());
        static::assertEquals('Test Cross Selling', $element->getCrossSelling()->getName());

        $lastPrice = 0;
        foreach ($element->getProducts() as $product) {
            $productPrice = $product->getCurrencyPrice(Defaults::CURRENCY);
            static::assertNotNull($productPrice);
            static::assertGreaterThanOrEqual($lastPrice, $productPrice->getGross());
            $lastPrice = $productPrice->getGross();
        }
    }

    public function testLoadForProductWithCloseoutAndFilterEnabledAllProductsOfOfStock(): void
    {
        // enable hideCloseoutProductsWhenOutOfStock filter
        $this->getContainer()->get(SystemConfigService::class)
            ->set('core.listing.hideCloseoutProductsWhenOutOfStock', true);

        $productId = Uuid::randomHex();

        $productData = $this->getProductData($productId);
        $productData['crossSellings'] = [[
            'name' => 'Test Cross Selling',
            'sortBy' => ProductCrossSellingDefinition::SORT_BY_PRICE,
            'sortDirection' => FieldSorting::ASCENDING,
            'active' => true,
            'limit' => 3,
            'productStreamId' => $this->createProductStream(true, true),
        ]];

        $this->productRepository->create([$productData], $this->salesChannelContext->getContext());

        $product = $this->productRepository->search(new Criteria([$productId]), $this->salesChannelContext->getContext())->get($productId);
        static::assertInstanceOf(ProductEntity::class, $product);
        $result = $this->route->load($product->getId(), new Request(), $this->salesChannelContext, new Criteria())->getResult();

        static::assertEquals(1, $result->count());
    }

    public function testLoadForProductWithProductCrossSellingAssignedProducts(): void
    {
        // enable hideCloseoutProductsWhenOutOfStock filter
        $this->getContainer()->get(SystemConfigService::class)
            ->set('core.listing.hideCloseoutProductsWhenOutOfStock', false);

        $productId = Uuid::randomHex();

        $productData = $this->getProductData($productId);
        $productData['crossSellings'] = [[
            'name' => 'Test Cross Selling',
            'sortBy' => ProductCrossSellingDefinition::SORT_BY_PRICE,
            'sortDirection' => FieldSorting::ASCENDING,
            'active' => true,
            'limit' => 3,
            'type' => 'productList',
            'assignedProducts' => $this->createAssignedProducts(true, true),
        ]];

        $this->productRepository->create([$productData], $this->salesChannelContext->getContext());

        $product = $this->productRepository->search(new Criteria([$productId]), $this->salesChannelContext->getContext())->get($productId);
        static::assertInstanceOf(ProductEntity::class, $product);
        $result = $this->route->load($product->getId(), new Request(), $this->salesChannelContext, new Criteria())->getResult();

        static::assertEquals(1, $result->count());

        $element = $result->first();
        static::assertNotNull($element);
        static::assertEquals(5, $element->getProducts()->count());
        static::assertEquals(5, $element->getCrossSelling()->getAssignedProducts()?->count());

        $this->browser->request(
            'POST',
            $this->getUrl($productId),
            [
                'includes' => [
                    'product' => ['id', 'name'],
                    'product_cross_selling' => ['id', 'name'],
                ],
            ]
        );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertCount(1, $response);
        static::assertArrayHasKey('crossSelling', $response[0]);
        static::assertArrayHasKey('name', $response[0]['crossSelling']);
        static::assertArrayHasKey('id', $response[0]['crossSelling']);
        static::assertEquals('Test Cross Selling', $response[0]['crossSelling']['name']);

        $expected = ['id', 'name', 'apiAlias'];
        sort($expected);

        static::assertIsArray($response[0]['crossSelling']);
        $properties = array_keys($response[0]['crossSelling']);
        sort($properties);
        static::assertEquals($expected, $properties);

        static::assertArrayHasKey('products', $response[0]);
        static::assertCount(5, $response[0]['products']);

        $properties = array_keys($response[0]['products'][0]);
        sort($properties);
        static::assertEquals($expected, $properties);
    }

    public function testLoadForProductWithProductCrossSellingAssignedProductsOutOfStock(): void
    {
        // enable hideCloseoutProductsWhenOutOfStock filter
        $this->getContainer()->get(SystemConfigService::class)
            ->set('core.listing.hideCloseoutProductsWhenOutOfStock', true);

        $productId = Uuid::randomHex();

        $productData = $this->getProductData($productId);
        $productData['crossSellings'] = [[
            'name' => 'Test Cross Selling',
            'sortBy' => ProductCrossSellingDefinition::SORT_BY_PRICE,
            'sortDirection' => FieldSorting::ASCENDING,
            'active' => true,
            'limit' => 3,
            'type' => 'productList',
            'assignedProducts' => $this->createAssignedProducts(true, true),
        ]];

        $this->productRepository->create([$productData], $this->salesChannelContext->getContext());

        $product = $this->productRepository->search(new Criteria([$productId]), $this->salesChannelContext->getContext())->get($productId);
        static::assertInstanceOf(ProductEntity::class, $product);
        $result = $this->route->load($product->getId(), new Request(), $this->salesChannelContext, new Criteria())->getResult();

        static::assertEquals(1, $result->count());

        $element = $result->first();
        static::assertNotNull($element);
        static::assertEquals(0, $element->getProducts()->count());
        static::assertEquals(5, $element->getCrossSelling()->getAssignedProducts()?->count());
    }

    /**
     * Shouldn't be necessary to test for loadForProducts() as the caller has to handle sorting of cross sellings
     */
    public function testLoadMultipleCrossSellingsOrderedByPosition(): void
    {
        $productId = Uuid::randomHex();

        $crossSellingIds = [
            Uuid::randomHex(),
            Uuid::randomHex(),
        ];
        $productData = $this->getProductData($productId);
        $productData['crossSellings'] = [[
            'id' => $crossSellingIds[0],
            'name' => 'First Cross Selling',
            'position' => 1,
            'active' => true,
            'productStreamId' => $this->createProductStream(),
        ], [
            'id' => $crossSellingIds[1],
            'name' => 'Second Cross Selling',
            'position' => 2,
            'active' => true,
            'productStreamId' => $this->createProductStream(),
        ]];

        $this->productRepository->create([$productData], $this->salesChannelContext->getContext());

        $result = $this->route->load($productId, new Request(), $this->salesChannelContext, new Criteria())->getResult();

        static::assertEquals(2, $result->count());
        foreach ($result as $index => $element) {
            static::assertEquals($crossSellingIds[$index], $element->getCrossSelling()->getId());
        }
    }

    /**
     * Shouldn't be necessary to test for loadForProducts() as the caller has to handle cross selling inheritance loading
     */
    public function testLoadCrossSellingsForVariantInheritedByParent(): void
    {
        $productId = Uuid::randomHex();
        $optionId = Uuid::randomHex();
        $variantId = Uuid::randomHex();

        $crossSellingIds = [
            Uuid::randomHex(),
            Uuid::randomHex(),
        ];
        $productData = $this->getProductData($productId);
        $productData['crossSellings'] = [[
            'id' => $crossSellingIds[0],
            'name' => 'First Cross Selling',
            'position' => 1,
            'active' => true,
            'productStreamId' => $this->createProductStream(),
        ], [
            'id' => $crossSellingIds[1],
            'name' => 'Second Cross Selling',
            'position' => 2,
            'active' => true,
            'productStreamId' => $this->createProductStream(),
        ]];
        $productData['configuratorSettings'] = [[
            'option' => [
                'id' => $optionId,
                'name' => 'Option',
                'position' => 0,
                'group' => [
                    'sortingType' => 'alphanumeric',
                    'displayType' => 'text',
                    'name' => 'test one group',
                ],
            ],
            'position' => 0,
        ]];
        $productData['children'] = [[
            'id' => $variantId,
            'productNumber' => Uuid::randomHex(),
            'stock' => 1,
            'options' => [
                [
                    'id' => $optionId,
                ],
            ],
        ]];

        $this->salesChannelContext->getContext()->setConsiderInheritance(true);
        $this->productRepository->create([$productData], $this->salesChannelContext->getContext());

        $result = $this->route->load($variantId, new Request(), $this->salesChannelContext, new Criteria())->getResult();

        static::assertEquals(2, $result->count());
        foreach ($result as $index => $element) {
            static::assertEquals($crossSellingIds[$index], $element->getCrossSelling()->getId());
        }
    }

    public function testCrossSellingEventSubscriberCanUpdateCriteria(): void
    {
        $eventDispatcher = new EventDispatcher();
        $productRepository = $this->getContainer()->get('product.repository');
        $eventDispatcher->addListener(
            ProductCrossSellingIdsCriteriaEvent::class,
            static function (ProductCrossSellingIdsCriteriaEvent $event) use ($productRepository): void {
                $ids = array_values($event->getCrossSelling()->getAssignedProducts()?->getProductIds() ?? []);

                $criteria = new Criteria();
                $criteria->addFilter(new EqualsAnyFilter('parentId', $ids));
                $crossSellingProducts = $productRepository->searchIds($criteria, $event->getContext())->getIds();
                $event->getCriteria()->setIds($crossSellingProducts);
            }
        );

        $route = new ProductCrossSellingRoute(
            $this->getContainer()->get('product_cross_selling.repository'),
            $eventDispatcher,
            $this->createMock(ProductStreamBuilderInterface::class),
            $this->getContainer()->get('sales_channel.product.repository'),
            $this->createMock(SystemConfigService::class),
            $this->createMock(ProductListingLoader::class),
            $this->createMock(AbstractProductCloseoutFilterFactory::class),
            new EventDispatcher()
        );

        $productId = Uuid::randomHex();

        $productData = $this->getProductData($productId);
        $productData['crossSellings'] = [[
            'name' => 'Test Cross Selling',
            'sortBy' => ProductCrossSellingDefinition::SORT_BY_PRICE,
            'sortDirection' => FieldSorting::ASCENDING,
            'active' => true,
            'limit' => 3,
            'type' => 'productList',
            'assignedProducts' => $this->createAssignedProducts(true, false, true),
        ]];

        $this->salesChannelContext->getContext()->setConsiderInheritance(true);
        $this->productRepository->create([$productData], $this->salesChannelContext->getContext());

        $product = $this->productRepository->search(new Criteria([$productId]), $this->salesChannelContext->getContext())->get($productId);
        static::assertInstanceOf(ProductEntity::class, $product);
        $result = $route->load($product->getId(), new Request(), $this->salesChannelContext, new Criteria())->getResult();
        static::assertEquals(1, $result->count());

        $element = $result->first();
        static::assertNotNull($element);
        static::assertEquals(5, $element->getProducts()->count());
        static::assertEquals(5, $element->getCrossSelling()->getAssignedProducts()?->count());
    }

    private function createProductStream(bool $includesIsCloseoutProducts = false, bool $noStock = false): string
    {
        $id = Uuid::randomHex();
        $randomProductIds = implode('|', array_column($this->createProducts($includesIsCloseoutProducts, $noStock), 'id'));

        $this->getContainer()->get('product_stream.repository')->create([
            [
                'id' => $id,
                'filters' => [
                    [
                        'type' => 'equalsAny',
                        'field' => 'id',
                        'value' => $randomProductIds,
                    ],
                ],
                'name' => 'testStream',
            ],
        ], $this->salesChannelContext->getContext());

        return $id;
    }

    /**
     * @return list<array{productId: string, position: int}>
     */
    private function createAssignedProducts(bool $includesIsCloseoutProducts = false, bool $noStock = false, bool $withChild = false): array
    {
        $assignedProducts = [];
        $randomProductIds = array_column($this->createProducts($includesIsCloseoutProducts, $noStock, $withChild), 'id');

        foreach ($randomProductIds as $index => $productId) {
            $assignedProducts[] = [
                'productId' => $productId,
                'position' => $index + 1,
            ];
        }

        return $assignedProducts;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function createProducts(bool $isCloseout = false, bool $noStock = false, bool $withChild = false): array
    {
        $manufacturerId = Uuid::randomHex();
        $taxId = Uuid::randomHex();
        $products = [];

        if ($isCloseout) {
            for ($i = 0; $i < 5; ++$i) {
                if ($noStock) {
                    $stock = 0;
                } else {
                    $stock = $i > 0 ? 0 : 1;
                }

                $products[] = $this->getProductData(null, $manufacturerId, $taxId, $withChild, $stock, $isCloseout);
            }
        } else {
            for ($i = 0; $i < 5; ++$i) {
                $products[] = $this->getProductData(null, $manufacturerId, $taxId, $withChild);
            }
        }

        $this->productRepository->create($products, $this->salesChannelContext->getContext());
        $this->addTaxDataToSalesChannel($this->salesChannelContext, end($products)['tax']);

        return $products;
    }

    /**
     * @return array<string, mixed>
     */
    private function getProductData(?string $id = null, ?string $manufacturerId = null, ?string $taxId = null, bool $withChild = false, int $stock = 1, bool $isCloseout = false): array
    {
        $price = random_int(0, 10);

        $product = [
            'id' => $id ?? Uuid::randomHex(),
            'productNumber' => Uuid::randomHex(),
            'stock' => $stock,
            'name' => 'Test',
            'isCloseout' => $isCloseout,
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => $price, 'net' => $price, 'linked' => false]],
            'manufacturer' => ['id' => $manufacturerId ?? Uuid::randomHex(), 'name' => 'test'],
            'tax' => ['id' => $taxId ?? Uuid::randomHex(), 'taxRate' => 17, 'name' => 'with id'],
            'visibilities' => [
                ['salesChannelId' => $this->salesChannelContext->getSalesChannel()->getId(), 'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL],
            ],
        ];

        if ($withChild) {
            $optionId = Uuid::randomHex();
            $product['configuratorSettings'] = [[
                'option' => [
                    'id' => $optionId,
                    'name' => 'Option',
                    'position' => 0,
                    'group' => [
                        'sortingType' => 'alphanumeric',
                        'displayType' => 'text',
                        'name' => 'test one group',
                    ],
                ],
                'position' => 0,
            ]];
            $product['children'] = [[
                'id' => Uuid::randomHex(),
                'productNumber' => Uuid::randomHex(),
                'stock' => 1,
                'options' => [
                    [
                        'id' => $optionId,
                    ],
                ],
            ]];
        }

        $this->addTaxDataToSalesChannel($this->salesChannelContext, $product['tax']);

        return $product;
    }

    private function getUrl(string $productId): string
    {
        return '/store-api/product/' . $productId . '/cross-selling';
    }
}
