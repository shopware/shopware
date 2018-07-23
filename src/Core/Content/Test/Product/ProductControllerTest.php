<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Product;

use Doctrine\DBAL\Connection;
use Ramsey\Uuid\Uuid;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\ORM\RepositoryInterface;
use Shopware\Core\Framework\Test\Api\ApiTestCase;

class ProductControllerTest extends ApiTestCase
{
    /**
     * @var RepositoryInterface
     */
    private $productRepository;

    /**
     * @var Connection
     */
    private $connection;

    protected function setUp()
    {
        parent::setUp();

        $this->productRepository = self::$container->get('product.repository');
        $this->connection = self::$container->get(Connection::class);
        $this->connection->beginTransaction();
        $this->connection->executeUpdate('DELETE FROM product');
    }

    public function tearDown()
    {
        $this->connection->rollBack();
        parent::tearDown();
    }

    public function testProductList(): void
    {
        $manufacturerId = Uuid::uuid4()->getHex();
        $taxId = Uuid::uuid4()->getHex();

        $this->productRepository->create([
            [
                'id' => Uuid::uuid4()->getHex(),
                'name' => 'Test',
                'price' => ['gross' => 10, 'net' => 9],
                'manufacturer' => ['id' => $manufacturerId, 'name' => 'test'],
                'tax' => ['id' => $taxId, 'taxRate' => 17, 'name' => 'with id'],
            ],
        ], Context::createDefaultContext(Defaults::TENANT_ID));

        $this->storefrontApiClient->request('GET', '/storefront-api/product');

        self::assertSame(200, $this->storefrontApiClient->getResponse()->getStatusCode(), $this->storefrontApiClient->getResponse()->getContent());

        $content = json_decode($this->storefrontApiClient->getResponse()->getContent(), true);

        $this->assertNotEmpty($content);
        $this->assertArrayHasKey('total', $content);
        $this->assertArrayHasKey('data', $content);
        $this->assertGreaterThan(0, $content['total']);
        $this->assertNotEmpty($content['data']);

        foreach ($content['data'] as $product) {
            $this->assertArrayHasKey('calculatedListingPrice', $product);
            $this->assertArrayHasKey('calculatedPriceRules', $product);
            $this->assertArrayHasKey('calculatedPrice', $product);
            $this->assertArrayHasKey('price', $product);
            $this->assertArrayHasKey('name', $product);
            $this->assertArrayHasKey('id', $product);
        }
    }

    public function testProductDetail(): void
    {
        $productId = Uuid::uuid4()->getHex();
        $manufacturerId = Uuid::uuid4()->toString();
        $taxId = Uuid::uuid4()->toString();

        $this->productRepository->create([
            [
                'id' => $productId,
                'name' => 'Test',
                'price' => ['gross' => 10, 'net' => 9],
                'manufacturer' => ['id' => $manufacturerId, 'name' => 'test'],
                'tax' => ['id' => $taxId, 'taxRate' => 17, 'name' => 'with id'],
            ],
        ], Context::createDefaultContext(Defaults::TENANT_ID));

        $this->storefrontApiClient->request('GET', '/storefront-api/product/' . $productId);

        self::assertSame(200, $this->storefrontApiClient->getResponse()->getStatusCode(), $this->storefrontApiClient->getResponse()->getContent());

        $content = json_decode($this->storefrontApiClient->getResponse()->getContent(), true);

        $this->assertEquals($productId, $content['data']['id']);
        $this->assertEquals(10, $content['data']['price']['gross']);
        $this->assertEquals('test', $content['data']['manufacturer']['name']);
        $this->assertEquals('with id', $content['data']['tax']['name']);
        $this->assertEquals(17, $content['data']['tax']['taxRate']);
    }
}
