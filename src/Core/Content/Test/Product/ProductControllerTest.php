<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Product;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelFunctionalTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;

class ProductControllerTest extends TestCase
{
    use SalesChannelFunctionalTestBehaviour;

    /**
     * @var EntityRepositoryInterface
     */
    private $productRepository;

    /**
     * @var Connection
     */
    private $connection;

    protected function setUp(): void
    {
        $this->productRepository = $this->getContainer()->get('product.repository');
        $this->connection = $this->getContainer()->get(Connection::class);
    }

    public function testProductList(): void
    {
        $manufacturerId = Uuid::randomHex();
        $taxId = Uuid::randomHex();

        $client = $this->getSalesChannelBrowser();
        $salesChannelId = $this->salesChannelIds[count($this->salesChannelIds) - 1];

        $this->productRepository->create([
            [
                'id' => Uuid::randomHex(),
                'productNumber' => Uuid::randomHex(),
                'stock' => 1,
                'name' => 'Test',
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
                'manufacturer' => ['id' => $manufacturerId, 'name' => 'test'],
                'tax' => ['id' => $taxId, 'taxRate' => 17, 'name' => 'with id'],
                'visibilities' => [
                    ['salesChannelId' => $salesChannelId, 'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL],
                ],
            ],
        ], Context::createDefaultContext());

        $client->request('GET', '/sales-channel-api/v' . PlatformRequest::API_VERSION . '/product');

        static::assertSame(200, $client->getResponse()->getStatusCode(), $client->getResponse()->getContent());

        $content = json_decode($client->getResponse()->getContent(), true);

        static::assertNotEmpty($content);
        static::assertArrayHasKey('total', $content);
        static::assertArrayHasKey('data', $content);
        static::assertGreaterThan(0, $content['total']);
        static::assertNotEmpty($content['data']);

        foreach ($content['data'] as $product) {
            static::assertArrayHasKey('calculatedListingPrice', $product);
            static::assertArrayHasKey('calculatedPrices', $product);
            static::assertArrayHasKey('calculatedPrice', $product);
            static::assertArrayNotHasKey('price', $product);
            static::assertArrayHasKey('name', $product);
            static::assertArrayHasKey('id', $product);
        }
    }

    public function testProductDetail(): void
    {
        $productId = Uuid::randomHex();
        $manufacturerId = Uuid::randomHex();
        $taxId = Uuid::randomHex();

        $client = $this->getSalesChannelBrowser();
        $salesChannelId = $this->salesChannelIds[count($this->salesChannelIds) - 1];

        $this->productRepository->create([
            [
                'id' => $productId,
                'productNumber' => Uuid::randomHex(),
                'stock' => 1,
                'name' => 'Test',
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
                'manufacturer' => ['id' => $manufacturerId, 'name' => 'test'],
                'tax' => ['id' => $taxId, 'taxRate' => 17, 'name' => 'with id'],
                'visibilities' => [
                    ['salesChannelId' => $salesChannelId, 'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL],
                ],
            ],
        ], Context::createDefaultContext());

        $client->request('GET', '/sales-channel-api/v' . PlatformRequest::API_VERSION . '/product/' . $productId);

        static::assertSame(200, $client->getResponse()->getStatusCode(), $client->getResponse()->getContent());

        $content = json_decode($client->getResponse()->getContent(), true);

        static::assertEquals($productId, $content['data']['id']);
        static::assertEquals(10, $content['data']['calculatedPrice']['totalPrice']);
        static::assertEquals('with id', $content['data']['tax']['name']);
        static::assertEquals(17, $content['data']['tax']['taxRate']);
    }

    public function testProductDetailWithInactiveProduct(): void
    {
        $productId = Uuid::randomHex();
        $manufacturerId = Uuid::randomHex();
        $taxId = Uuid::randomHex();

        $client = $this->getSalesChannelBrowser();
        $salesChannelId = $this->salesChannelIds[count($this->salesChannelIds) - 1];

        $this->productRepository->create([
            [
                'id' => $productId,
                'productNumber' => Uuid::randomHex(),
                'name' => 'Test',
                'stock' => 1,
                'active' => false,
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
                'manufacturer' => ['id' => $manufacturerId, 'name' => 'test'],
                'tax' => ['id' => $taxId, 'taxRate' => 17, 'name' => 'with id'],

                'visibilities' => [
                    ['salesChannelId' => $salesChannelId, 'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL],
                ],
            ],
        ], Context::createDefaultContext());

        $client->request('GET', '/sales-channel-api/v' . PlatformRequest::API_VERSION . '/product/' . $productId);

        static::assertSame(404, $client->getResponse()->getStatusCode(), $client->getResponse()->getContent());
    }
}
