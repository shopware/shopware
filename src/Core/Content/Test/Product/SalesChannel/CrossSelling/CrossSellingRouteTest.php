<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Product\SalesChannel\CrossSelling;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Test\Cart\Common\Generator;
use Shopware\Core\Content\Product\Aggregate\ProductCrossSelling\ProductCrossSellingDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\SalesChannel\CrossSelling\ProductCrossSellingRoute;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\TaxAddToSalesChannelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\HttpFoundation\Request;

/**
 * @group slow
 * @group store-api
 */
class CrossSellingRouteTest extends TestCase
{
    use IntegrationTestBehaviour;
    use TaxAddToSalesChannelTestBehaviour;
    use SalesChannelApiTestBehaviour;

    /**
     * @var SalesChannelContext
     */
    private $salesChannelContext;

    /**
     * @var EntityRepositoryInterface
     */
    private $productRepository;

    /**
     * @var ProductCrossSellingRoute
     */
    private $route;

    /**
     * @var \Symfony\Bundle\FrameworkBundle\KernelBrowser
     */
    private $browser;

    public function setUp(): void
    {
        $this->salesChannelContext = Generator::createSalesChannelContext(
            null,
            null,
            null,
            (new SalesChannelEntity())->assign([
                'id' => Defaults::SALES_CHANNEL,
                'taxCalculationType' => SalesChannelDefinition::CALCULATION_TYPE_VERTICAL,
            ])
        );
        $this->productRepository = $this->getContainer()->get('product.repository');
        $this->route = $this->getContainer()->get(ProductCrossSellingRoute::class);

        $this->browser = $this->createCustomSalesChannelBrowser([
            'id' => Defaults::SALES_CHANNEL,
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
        static::assertEquals(3, $element->getTotal());
        static::assertEquals('Test Cross Selling', $element->getCrossSelling()->getName());

        $lastPrice = 0;
        foreach ($element->getProducts() as $product) {
            static::assertGreaterThanOrEqual($lastPrice, $product->getCurrencyPrice(Defaults::CURRENCY)->getGross());
            $lastPrice = $product->getCurrencyPrice(Defaults::CURRENCY)->getGross();
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
        $result = $this->route->load($product->getId(), new Request(), $this->salesChannelContext, new Criteria())->getResult();

        static::assertEquals(1, $result->count());

        $element = $result->first();
        static::assertEquals(3, $element->getTotal());
        static::assertEquals('Test Cross Selling', $element->getCrossSelling()->getName());

        $lastPrice = 0;
        foreach ($element->getProducts() as $product) {
            static::assertGreaterThanOrEqual($lastPrice, $product->getCurrencyPrice(Defaults::CURRENCY)->getGross());
            $lastPrice = $product->getCurrencyPrice(Defaults::CURRENCY)->getGross();
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
        $result = $this->route->load($product->getId(), new Request(), $this->salesChannelContext, new Criteria())->getResult();

        static::assertEquals(1, $result->count());

        $element = $result->first();

        static::assertEquals(3, $element->getTotal());
        static::assertEquals('Test Cross Selling', $element->getCrossSelling()->getName());

        $lastPrice = 0;
        foreach ($element->getProducts() as $product) {
            static::assertGreaterThanOrEqual($lastPrice, $product->getCurrencyPrice(Defaults::CURRENCY)->getGross());
            $lastPrice = $product->getCurrencyPrice(Defaults::CURRENCY)->getGross();
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
        $result = $this->route->load($product->getId(), new Request(), $this->salesChannelContext, new Criteria())->getResult();

        static::assertEquals(1, $result->count());

        $element = $result->first();

        static::assertEquals(1, $element->getTotal());
        static::assertEquals('Test Cross Selling', $element->getCrossSelling()->getName());

        $lastPrice = 0;
        foreach ($element->getProducts() as $product) {
            static::assertGreaterThanOrEqual($lastPrice, $product->getCurrencyPrice(Defaults::CURRENCY)->getGross());
            $lastPrice = $product->getCurrencyPrice(Defaults::CURRENCY)->getGross();
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
        $result = $this->route->load($product->getId(), new Request(), $this->salesChannelContext, new Criteria())->getResult();

        static::assertEquals(1, $result->count());

        $element = $result->first();

        static::assertEquals(5, $element->getProducts()->count());
        static::assertEquals(5, $element->getCrossSelling()->getAssignedProducts()->count());

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

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertCount(1, $response);
        static::assertArrayHasKey('crossSelling', $response[0]);
        static::assertArrayHasKey('name', $response[0]['crossSelling']);
        static::assertArrayHasKey('id', $response[0]['crossSelling']);
        static::assertEquals('Test Cross Selling', $response[0]['crossSelling']['name']);

        $expected = ['id', 'name', 'apiAlias'];
        sort($expected);

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
        $result = $this->route->load($product->getId(), new Request(), $this->salesChannelContext, new Criteria())->getResult();

        static::assertEquals(1, $result->count());

        $element = $result->first();

        static::assertEquals(0, $element->getProducts()->count());
        static::assertEquals(5, $element->getCrossSelling()->getAssignedProducts()->count());
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

    private function createProductStream(?bool $includesIsCloseoutProducts = false, ?bool $noStock = false): string
    {
        /** @var EntityRepositoryInterface $streamRepository */
        $streamRepository = $this->getContainer()->get('product_stream.repository');
        $id = Uuid::randomHex();
        $randomProductIds = implode('|', array_column($this->createProducts($includesIsCloseoutProducts, $noStock), 'id'));

        $streamRepository->create([
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

    private function createAssignedProducts(?bool $includesIsCloseoutProducts = false, ?bool $noStock = false): array
    {
        $assignedProducts = [];
        $randomProductIds = array_column($this->createProducts($includesIsCloseoutProducts, $noStock), 'id');

        foreach ($randomProductIds as $index => $productId) {
            $assignedProducts[] = [
                'productId' => $productId,
                'position' => $index + 1,
            ];
        }

        return $assignedProducts;
    }

    private function createProducts(?bool $isCloseout = false, ?bool $noStock = false): array
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

                $products[] = $this->getProductData(null, $manufacturerId, $taxId, $stock, $isCloseout);
            }
        } else {
            for ($i = 0; $i < 5; ++$i) {
                $products[] = $this->getProductData(null, $manufacturerId, $taxId);
            }
        }

        $this->productRepository->create($products, $this->salesChannelContext->getContext());
        $this->addTaxDataToSalesChannel($this->salesChannelContext, end($products)['tax']);

        return $products;
    }

    private function getProductData(?string $id = null, ?string $manufacturerId = null, ?string $taxId = null, ?int $stock = 1, ?bool $isCloseout = false): array
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

        $this->addTaxDataToSalesChannel($this->salesChannelContext, $product['tax']);

        return $product;
    }

    private function getUrl(string $productId): string
    {
        return '/store-api/product/' . $productId . '/cross-selling';
    }
}
