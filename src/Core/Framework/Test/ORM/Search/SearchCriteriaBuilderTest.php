<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\ORM\Search;

use Doctrine\DBAL\Connection;
use Ramsey\Uuid\Uuid;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\ORM\RepositoryInterface;
use Shopware\Core\Framework\ORM\Search\Criteria;
use Shopware\Core\Framework\Test\Api\ApiTestCase;

class SearchCriteriaBuilderTest extends ApiTestCase
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

    /**
     * FETCH-COUNT
     */
    public function testListFetchCount(): void
    {
        $manufacturerId = Uuid::uuid4()->getHex();
        $payload = ['manufacturer' => ['id' => $manufacturerId, 'name' => 'foobar']];

        for ($i = 0; $i < 35; ++$i) {
            $this->createProduct($payload);
        }

        // no count, equals to fetched entities
        $this->storefrontApiClient->request('GET', '/storefront-api/product', ['fetch-count' => Criteria::FETCH_COUNT_NONE, 'filter' => ['product.manufacturer.id' => $manufacturerId], 'limit' => 5]);
        $this->assertSame(200, $this->storefrontApiClient->getResponse()->getStatusCode(), $this->storefrontApiClient->getResponse()->getContent());
        $content = json_decode($this->storefrontApiClient->getResponse()->getContent(), true);

        $this->assertEquals(5, $content['total']);

        // calculates all matching rows
        $this->storefrontApiClient->request('GET', '/storefront-api/product', ['fetch-count' => Criteria::FETCH_COUNT_TOTAL, 'filter' => ['product.manufacturer.id' => $manufacturerId], 'limit' => 5]);
        $this->assertSame(200, $this->storefrontApiClient->getResponse()->getStatusCode());
        $content = json_decode($this->storefrontApiClient->getResponse()->getContent(), true);

        $this->assertEquals(35, $content['total']);

        // returns the count of 5 next pages plus 1 if there are more
        $this->storefrontApiClient->request('GET', '/storefront-api/product', ['fetch-count' => Criteria::FETCH_COUNT_NEXT_PAGES, 'filter' => ['product.manufacturer.id' => $manufacturerId], 'limit' => 5]);
        $this->assertSame(200, $this->storefrontApiClient->getResponse()->getStatusCode());
        $content = json_decode($this->storefrontApiClient->getResponse()->getContent(), true);

        $this->assertEquals(31, $content['total']);
    }

    /**
     * SORTING
     */
    public function testSortingAscending(): void
    {
        $this->createProduct(['stock' => 10]);
        $this->createProduct(['stock' => 20]);
        $this->createProduct(['stock' => 30]);

        $this->storefrontApiClient->request('GET', '/storefront-api/product', ['sort' => 'product.stock']);
        $this->assertSame(200, $this->storefrontApiClient->getResponse()->getStatusCode());
        $content = json_decode($this->storefrontApiClient->getResponse()->getContent(), true);

        $this->assertEquals(10, $content['data'][0]['stock']);
        $this->assertEquals(20, $content['data'][1]['stock']);
        $this->assertEquals(30, $content['data'][2]['stock']);
    }

    public function testSortingDescending(): void
    {
        $this->createProduct(['stock' => 10]);
        $this->createProduct(['stock' => 20]);
        $this->createProduct(['stock' => 30]);

        $this->storefrontApiClient->request('GET', '/storefront-api/product', ['sort' => '-product.stock']);
        $this->assertSame(200, $this->storefrontApiClient->getResponse()->getStatusCode());
        $content = json_decode($this->storefrontApiClient->getResponse()->getContent(), true);

        $this->assertEquals(30, $content['data'][0]['stock']);
        $this->assertEquals(20, $content['data'][1]['stock']);
        $this->assertEquals(10, $content['data'][2]['stock']);
    }

    public function testMultipleSorting(): void
    {
        $product1 = $this->createProduct(['stock' => 10, 'minStock' => 10]);
        $product2 = $this->createProduct(['stock' => 20, 'minStock' => 20]);
        $product3 = $this->createProduct(['stock' => 20, 'minStock' => 30]);

        /*
         * Sort by stock ASC, minStock ASC
         */
        $this->storefrontApiClient->request('GET', '/storefront-api/product', ['sort' => 'product.stock,product.minStock']);
        $this->assertSame(200, $this->storefrontApiClient->getResponse()->getStatusCode());
        $content = json_decode($this->storefrontApiClient->getResponse()->getContent(), true);

        $expectedSort = [$product1, $product2, $product3];
        $actualSort = array_column($content['data'], 'id');

        $this->assertEquals($expectedSort, $actualSort);

        /*
         * Sort by stock ASC, minStock DESC
         */
        $this->storefrontApiClient->request('GET', '/storefront-api/product', ['sort' => 'product.stock,-product.minStock']);
        $this->assertSame(200, $this->storefrontApiClient->getResponse()->getStatusCode());
        $content = json_decode($this->storefrontApiClient->getResponse()->getContent(), true);

        $expectedSort = [$product1, $product3, $product2];
        $actualSort = array_column($content['data'], 'id');

        $this->assertEquals($expectedSort, $actualSort);

        /*
         * Sort by stock DESC, minStock ASC
         */
        $this->storefrontApiClient->request('GET', '/storefront-api/product', ['sort' => '-product.stock,product.minStock']);
        $this->assertSame(200, $this->storefrontApiClient->getResponse()->getStatusCode());
        $content = json_decode($this->storefrontApiClient->getResponse()->getContent(), true);

        $expectedSort = [$product2, $product3, $product1];
        $actualSort = array_column($content['data'], 'id');

        $this->assertEquals($expectedSort, $actualSort);

        /*
         * Sort by stock DESC, minStock DESC
         */
        $this->storefrontApiClient->request('GET', '/storefront-api/product', ['sort' => '-product.stock,-product.minStock']);
        $this->assertSame(200, $this->storefrontApiClient->getResponse()->getStatusCode());
        $content = json_decode($this->storefrontApiClient->getResponse()->getContent(), true);

        $expectedSort = [$product3, $product2, $product1];
        $actualSort = array_column($content['data'], 'id');

        $this->assertEquals($expectedSort, $actualSort);
    }

    public function testSortingWithInvalidField(): void
    {
        $this->storefrontApiClient->request('GET', '/storefront-api/product', ['sort' => 'product.unknown']);
        $this->assertSame(400, $this->storefrontApiClient->getResponse()->getStatusCode());
        $content = json_decode($this->storefrontApiClient->getResponse()->getContent(), true);

        $this->assertEquals('Field "unknown" in entity "product" was not found.', $content['errors'][0]['detail']);
    }

    public function testSortingWithEmptyField(): void
    {
        $this->storefrontApiClient->request('GET', '/storefront-api/product', ['sort' => '']);
        $this->assertSame(400, $this->storefrontApiClient->getResponse()->getStatusCode());
        $content = json_decode($this->storefrontApiClient->getResponse()->getContent(), true);

        $this->assertEquals('A value for the sort parameter is required.', $content['errors'][0]['detail']);
        $this->assertEquals('/sort', $content['errors'][0]['source']['pointer']);
    }

    /**
     * OFFSET
     */
    public function testOffset(): void
    {
        $manufacturerId = Uuid::uuid4()->getHex();
        $payload = ['manufacturer' => ['id' => $manufacturerId, 'name' => 'foobar']];

        $ids = [];
        for ($i = 0; $i < 20; ++$i) {
            $ids[] = $this->createProduct($payload);
        }

        $this->storefrontApiClient->request('GET', '/storefront-api/product', ['offset' => 10, 'filter' => ['product.manufacturer.id' => $manufacturerId], 'sort' => 'product.id']);
        $this->assertSame(200, $this->storefrontApiClient->getResponse()->getStatusCode());
        $content = json_decode($this->storefrontApiClient->getResponse()->getContent(), true);

        sort($ids);

        $expectedIds = array_slice($ids, 10);
        $actualIds = array_column($content['data'], 'id');

        $this->assertEquals($expectedIds, $actualIds);
    }

    public function testOffsetWithNumericString(): void
    {
        $manufacturerId = Uuid::uuid4()->getHex();
        $payload = ['manufacturer' => ['id' => $manufacturerId, 'name' => 'foobar']];

        $ids = [];
        for ($i = 0; $i < 20; ++$i) {
            $ids[] = $this->createProduct($payload);
        }

        $this->storefrontApiClient->request('GET', '/storefront-api/product', ['offset' => '10', 'filter' => ['product.manufacturer.id' => $manufacturerId], 'sort' => 'product.id']);
        $this->assertSame(200, $this->storefrontApiClient->getResponse()->getStatusCode());
        $content = json_decode($this->storefrontApiClient->getResponse()->getContent(), true);

        sort($ids);

        $expectedIds = array_slice($ids, 10);
        $actualIds = array_column($content['data'], 'id');

        $this->assertEquals($expectedIds, $actualIds);
    }

    public function testNegativeOffset(): void
    {
        $this->storefrontApiClient->request('GET', '/storefront-api/product', ['offset' => -1]);
        $this->assertSame(400, $this->storefrontApiClient->getResponse()->getStatusCode());
        $content = json_decode($this->storefrontApiClient->getResponse()->getContent(), true);

        $this->assertEquals('The offset parameter must be a positive integer. Given: -1', $content['errors'][0]['detail']);
        $this->assertEquals('/offset', $content['errors'][0]['source']['pointer']);
    }

    public function testNonIntegerOffset(): void
    {
        $this->storefrontApiClient->request('GET', '/storefront-api/product', ['offset' => 'foo']);
        $this->assertSame(400, $this->storefrontApiClient->getResponse()->getStatusCode());
        $content = json_decode($this->storefrontApiClient->getResponse()->getContent(), true);

        $this->assertEquals('The offset parameter must be a positive integer. Given: foo', $content['errors'][0]['detail']);
        $this->assertEquals('/offset', $content['errors'][0]['source']['pointer']);
    }

    public function testEmptyOffset(): void
    {
        $this->storefrontApiClient->request('GET', '/storefront-api/product', ['offset' => '']);
        $this->assertSame(400, $this->storefrontApiClient->getResponse()->getStatusCode());
        $content = json_decode($this->storefrontApiClient->getResponse()->getContent(), true);

        $this->assertEquals('The offset parameter must be a positive integer. Given: (empty)', $content['errors'][0]['detail']);
        $this->assertEquals('/offset', $content['errors'][0]['source']['pointer']);
    }

    /**
     * LIMIT
     */
    public function testLimit(): void
    {
        $manufacturerId = Uuid::uuid4()->getHex();
        $payload = ['manufacturer' => ['id' => $manufacturerId, 'name' => 'foobar']];

        for ($i = 0; $i < 10; ++$i) {
            $this->createProduct($payload);
        }

        $this->storefrontApiClient->request('GET', '/storefront-api/product', ['limit' => 5, 'filter' => ['product.manufacturer.id' => $manufacturerId], 'sort' => 'product.id']);
        $this->assertSame(200, $this->storefrontApiClient->getResponse()->getStatusCode());
        $content = json_decode($this->storefrontApiClient->getResponse()->getContent(), true);

        $this->assertCount(5, $content['data']);
    }

    public function testLimitWithNumericString(): void
    {
        $manufacturerId = Uuid::uuid4()->getHex();
        $payload = ['manufacturer' => ['id' => $manufacturerId, 'name' => 'foobar']];

        for ($i = 0; $i < 10; ++$i) {
            $this->createProduct($payload);
        }

        $this->storefrontApiClient->request('GET', '/storefront-api/product', ['limit' => '5', 'filter' => ['product.manufacturer.id' => $manufacturerId], 'sort' => 'product.id']);
        $this->assertSame(200, $this->storefrontApiClient->getResponse()->getStatusCode());
        $content = json_decode($this->storefrontApiClient->getResponse()->getContent(), true);

        $this->assertCount(5, $content['data']);
    }

    public function testNegativeLimit(): void
    {
        $this->storefrontApiClient->request('GET', '/storefront-api/product', ['limit' => 0]);
        $this->assertSame(400, $this->storefrontApiClient->getResponse()->getStatusCode());
        $content = json_decode($this->storefrontApiClient->getResponse()->getContent(), true);

        $this->assertEquals('The limit parameter must be a positive integer greater or equals than 1. Given: 0', $content['errors'][0]['detail']);
        $this->assertEquals('/limit', $content['errors'][0]['source']['pointer']);

        $this->storefrontApiClient->request('GET', '/storefront-api/product', ['limit' => -1]);
        $this->assertSame(400, $this->storefrontApiClient->getResponse()->getStatusCode());
        $content = json_decode($this->storefrontApiClient->getResponse()->getContent(), true);

        $this->assertEquals('The limit parameter must be a positive integer greater or equals than 1. Given: -1', $content['errors'][0]['detail']);
        $this->assertEquals('/limit', $content['errors'][0]['source']['pointer']);
    }

    public function testNonIntegerLimit(): void
    {
        $this->storefrontApiClient->request('GET', '/storefront-api/product', ['limit' => 'foo']);
        $this->assertSame(400, $this->storefrontApiClient->getResponse()->getStatusCode());
        $content = json_decode($this->storefrontApiClient->getResponse()->getContent(), true);

        $this->assertEquals('The limit parameter must be a positive integer greater or equals than 1. Given: foo', $content['errors'][0]['detail']);
        $this->assertEquals('/limit', $content['errors'][0]['source']['pointer']);
    }

    public function testEmptyLimit(): void
    {
        $this->storefrontApiClient->request('GET', '/storefront-api/product', ['limit' => '']);
        $this->assertSame(400, $this->storefrontApiClient->getResponse()->getStatusCode());
        $content = json_decode($this->storefrontApiClient->getResponse()->getContent(), true);

        $this->assertEquals('The limit parameter must be a positive integer greater or equals than 1. Given: (empty)', $content['errors'][0]['detail']);
        $this->assertEquals('/limit', $content['errors'][0]['source']['pointer']);
    }

    public function testMultipleErrorStack(): void
    {
        $query = [
            'limit' => '',
            'offset' => '',
            'filter' => [
                ['type' => 'bar'],
                ['type' => 'term', 'field' => 'foo', 'value' => ''],
                ['type' => 'nested', 'queries' => [
                    ['type' => 'foo'],
                    ['type' => 'terms', 'value' => 'wusel'],
                ]],
            ],
        ];

        $this->storefrontApiClient->request('GET', '/storefront-api/product', $query);
        $this->assertSame(400, $this->storefrontApiClient->getResponse()->getStatusCode());
        $content = json_decode($this->storefrontApiClient->getResponse()->getContent(), true);

        $this->assertCount(6, $content['errors'], print_r($content['errors'], true));
        $this->assertEquals('/offset', $content['errors'][0]['source']['pointer']);
        $this->assertEquals('/limit', $content['errors'][1]['source']['pointer']);
        $this->assertEquals('/filter/0/type', $content['errors'][2]['source']['pointer']);
        $this->assertEquals('/filter/1/value', $content['errors'][3]['source']['pointer']);
        $this->assertEquals('/filter/2/queries/0/type', $content['errors'][4]['source']['pointer']);
        $this->assertEquals('/filter/2/queries/1/field', $content['errors'][5]['source']['pointer']);
    }

    private function createProduct(array $parameters = []): string
    {
        $id = Uuid::uuid4()->getHex();

        $defaults = [
            'id' => $id,
            'name' => 'Test',
            'price' => ['gross' => 10, 'net' => 9],
            'manufacturer' => ['id' => Uuid::uuid4()->getHex(), 'name' => 'test'],
            'tax' => ['id' => Uuid::uuid4()->getHex(), 'taxRate' => 17, 'name' => 'with id'],
        ];

        $parameters = array_merge($defaults, $parameters);

        $this->productRepository->create([$parameters], Context::createDefaultContext(Defaults::TENANT_ID));

        return $id;
    }
}
