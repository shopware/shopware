<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Product\SalesChannel\CrossSelling;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductCrossSelling\ProductCrossSellingDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

class SalesChannelCrossSellingControllerTest extends TestCase
{
    use SalesChannelApiTestBehaviour;
    use IntegrationTestBehaviour;

    /**
     * @var KernelBrowser
     */
    private $browser;

    /**
     * @var EntityRepositoryInterface
     */
    private $productRepository;

    public function setUp(): void
    {
        $this->browser = $this->getSalesChannelBrowser();
        $this->productRepository = $this->getContainer()->get('product.repository');
    }

    public function testGetCrossSelling(): void
    {
        $productId = Uuid::randomHex();

        $productData = $this->getProductData($productId);
        $productData['crossSellings'] = [[
            'name' => 'Test Cross Selling',
            'sortBy' => ProductCrossSellingDefinition::SORT_BY_PRICE,
            'sortDirection' => FieldSorting::ASCENDING,
            'type' => 'productStream',
            'active' => true,
            'limit' => 3,
            'productStreamId' => $this->createProductStream(),
        ]];

        $this->productRepository->create([$productData], Context::createDefaultContext());

        $this->browser->request('GET', sprintf('/sales-channel-api/v%d/product/%s/cross-selling', PlatformRequest::API_VERSION, $productId));
        $response = $this->browser->getResponse();

        static::assertEquals(200, $response->getStatusCode());

        $result = json_decode($response->getContent(), true)['data'];
        static::assertCount(1, $result);

        $element = $result[0];
        static::assertEquals(3, $element['total']);
        static::assertEquals('Test Cross Selling', $element['crossSelling']['name']);

        $lastPrice = 0;
        foreach ($element['products'] as $product) {
            static::assertGreaterThanOrEqual($lastPrice, $product['price'][0]['gross']);
            $lastPrice = $product['price'][0]['gross'];
        }
    }

    public function testGetCrossSellingMultipleCrossSellingsOrderedByPosition(): void
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
            'type' => 'productStream',
            'active' => true,
            'productStreamId' => $this->createProductStream(),
        ], [
            'id' => $crossSellingIds[1],
            'name' => 'Second Cross Selling',
            'position' => 2,
            'type' => 'productStream',
            'active' => true,
            'productStreamId' => $this->createProductStream(),
        ]];

        $this->productRepository->create([$productData], Context::createDefaultContext());

        $this->browser->request('GET', sprintf('/sales-channel-api/v%d/product/%s/cross-selling', PlatformRequest::API_VERSION, $productId));
        $response = $this->browser->getResponse();

        static::assertEquals(200, $response->getStatusCode());

        $result = json_decode($response->getContent(), true)['data'];
        static::assertCount(2, $result);

        foreach ($result as $index => $element) {
            static::assertEquals($crossSellingIds[$index], $element['crossSelling']['id']);
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
        ], Context::createDefaultContext());

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

        $this->productRepository->create($products, Context::createDefaultContext());

        return $products;
    }

    private function getProductData(?string $id = null, ?string $manufacturerId = null, ?string $taxId = null): array
    {
        $price = random_int(0, 10);

        return [
            'id' => $id ?? Uuid::randomHex(),
            'productNumber' => Uuid::randomHex(),
            'stock' => 1,
            'name' => 'Test',
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => $price, 'net' => $price, 'linked' => false]],
            'manufacturer' => ['id' => $manufacturerId ?? Uuid::randomHex(), 'name' => 'test'],
            'tax' => ['id' => $taxId ?? Uuid::randomHex(), 'taxRate' => 17, 'name' => 'with id'],
            'visibilities' => [
                ['salesChannelId' => $this->getSalesChannelApiSalesChannelId(), 'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL],
            ],
        ];
    }
}
