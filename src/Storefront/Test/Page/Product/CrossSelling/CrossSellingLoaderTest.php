<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Page\Product\CrossSelling;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Test\Cart\Common\Generator;
use Shopware\Core\Content\Product\Aggregate\ProductCrossSelling\ProductCrossSellingDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\TaxAddToSalesChannelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Storefront\Page\Product\CrossSelling\CrossSellingLoader;

class CrossSellingLoaderTest extends TestCase
{
    use IntegrationTestBehaviour;
    use TaxAddToSalesChannelTestBehaviour;

    /**
     * @var SalesChannelContext
     */
    private $salesChannelContext;

    /**
     * @var EntityRepositoryInterface
     */
    private $productRepository;

    /**
     * @var CrossSellingLoader
     */
    private $crossSellingLoader;

    public function setUp(): void
    {
        $this->salesChannelContext = Generator::createSalesChannelContext(
            null,
            null,
            null,
            (new SalesChannelEntity())->assign(['id' => Defaults::SALES_CHANNEL])
        );
        $this->productRepository = $this->getContainer()->get('product.repository');
        $this->crossSellingLoader = $this->getContainer()->get(CrossSellingLoader::class);
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

        $result = $this->crossSellingLoader->load($productId, $this->salesChannelContext);

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
        $result = $this->crossSellingLoader->loadForProduct($product, $this->salesChannelContext);

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

        $result = $this->crossSellingLoader->load($productId, $this->salesChannelContext);

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

        $this->productRepository->create([$productData], $this->salesChannelContext->getContext());

        $result = $this->crossSellingLoader->load($variantId, $this->salesChannelContext);

        static::assertEquals(2, $result->count());
        foreach ($result as $index => $element) {
            static::assertEquals($crossSellingIds[$index], $element->getCrossSelling()->getId());
        }
    }

    private function createProductStream(): string
    {
        /** @var EntityRepositoryInterface $streamRepository */
        $streamRepository = $this->getContainer()->get('product_stream.repository');
        $id = Uuid::randomHex();
        $randomProductIds = implode('|', array_column($this->createProducts(), 'id'));

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

    private function createProducts(): array
    {
        $manufacturerId = Uuid::randomHex();
        $taxId = Uuid::randomHex();
        $products = [];

        for ($i = 0; $i < 5; ++$i) {
            $products[] = $this->getProductData(null, $manufacturerId, $taxId);
        }

        $this->productRepository->create($products, $this->salesChannelContext->getContext());
        $this->addTaxDataToSalesChannel($this->salesChannelContext, end($products)['tax']);

        return $products;
    }

    private function getProductData(?string $id = null, ?string $manufacturerId = null, ?string $taxId = null): array
    {
        $price = random_int(0, 10);

        $product = [
            'id' => $id ?? Uuid::randomHex(),
            'productNumber' => Uuid::randomHex(),
            'stock' => 1,
            'name' => 'Test',
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
}
