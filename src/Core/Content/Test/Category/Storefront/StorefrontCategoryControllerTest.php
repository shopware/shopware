<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Category\Storefront;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Category\Exception\CategoryNotFoundException;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\ORM\RepositoryInterface;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Core\Framework\Test\Api\ApiTestCase;

class StorefrontCategoryControllerTest extends ApiTestCase
{
    /**
     * @var RepositoryInterface
     */
    private $repository;

    /**
     * @var Context
     */
    private $context;

    /**
     * @var Connection
     */
    private $connection;

    protected function setUp()
    {
        parent::setUp();
        $this->connection = self::$container->get(Connection::class);
        $this->repository = self::$container->get('category.repository');
        $this->context = Context::createDefaultContext(Defaults::TENANT_ID);
        $this->connection->beginTransaction();
        $this->connection->executeUpdate('DELETE FROM category');
    }

    public function tearDown()
    {
        $this->connection->rollBack();
        parent::tearDown();
    }

    public function testCategoryListRoute()
    {
        $id = Uuid::uuid4()->getHex();

        $this->repository->create([
            ['id' => $id, 'name' => 'Test category'],
        ], $this->context);

        $this->storefrontApiClient->request('GET', '/storefront-api/category');

        $response = $this->storefrontApiClient->getResponse();

        $this->assertSame(200, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);

        $this->assertNotEmpty($content);
        $this->assertArrayHasKey('data', $content);
        $this->assertArrayHasKey('total', $content);
        $this->assertGreaterThanOrEqual(1, $content['total']);
    }

    public function testCategoryDetailRoute()
    {
        $id = Uuid::uuid4()->getHex();

        $this->repository->create([
            ['id' => $id, 'name' => 'Test category'],
        ], $this->context);

        $this->storefrontApiClient->request('GET', '/storefront-api/category/' . $id);

        $response = $this->storefrontApiClient->getResponse();

        $this->assertSame(200, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);

        $this->assertNotEmpty($content);
        $this->assertArrayHasKey('data', $content);
        $this->assertSame('Test category', $content['data']['name']);
    }

    public function testSortingOnListRoute()
    {
        $categoryC = Uuid::uuid4()->getHex();
        $categoryA = Uuid::uuid4()->getHex();
        $categoryB = Uuid::uuid4()->getHex();

        $this->repository->create([
            ['id' => $categoryC, 'name' => 'Category C'],
            ['id' => $categoryA, 'name' => 'Category A'],
            ['id' => $categoryB, 'name' => 'Category B'],
        ], $this->context);

        $this->storefrontApiClient->request('GET', '/storefront-api/category?sort=name');

        $response = $this->storefrontApiClient->getResponse();
        $content = json_decode($response->getContent(), true);
        $this->assertNotEmpty($content);
        $ids = array_column($content['data'], 'id');
        $this->assertSame([$categoryA, $categoryB, $categoryC], $ids);

        $this->storefrontApiClient->request('GET', '/storefront-api/category?sort=-name');
        $response = $this->storefrontApiClient->getResponse();
        $content = json_decode($response->getContent(), true);
        $this->assertNotEmpty($content);
        $ids = array_column($content['data'], 'id');
        $this->assertSame([$categoryC, $categoryB, $categoryA], $ids);
    }

    public function testTermOnListRoute()
    {
        $categoryC = Uuid::uuid4()->getHex();
        $categoryA = Uuid::uuid4()->getHex();
        $categoryB = Uuid::uuid4()->getHex();

        $this->repository->create([
            ['id' => $categoryC, 'name' => 'Matching name'],
            ['id' => $categoryA, 'name' => 'Not'],
            ['id' => $categoryB, 'name' => 'Matching name'],
        ], $this->context);

        $this->storefrontApiClient->request('GET', '/storefront-api/category?term=Matching');

        $response = $this->storefrontApiClient->getResponse();
        $content = json_decode($response->getContent(), true);
        $this->assertNotEmpty($content);
        $this->assertSame(2, $content['total']);

        $ids = array_column($content['data'], 'id');
        $this->assertContains($categoryC, $ids);
        $this->assertContains($categoryB, $ids);
    }

    public function testFilterOnListRoute()
    {
        $categoryC = Uuid::uuid4()->getHex();
        $categoryA = Uuid::uuid4()->getHex();
        $categoryB = Uuid::uuid4()->getHex();

        $this->repository->create([
            ['id' => $categoryC, 'name' => 'Matching name', 'active' => true],
            ['id' => $categoryA, 'name' => 'Not', 'active' => false],
            ['id' => $categoryB, 'name' => 'Matching name', 'active' => false],
        ], $this->context);

        $params = http_build_query([
            'filter' => [
                'name' => 'Matching name',
                'active' => true,
            ],
        ]);

        $this->storefrontApiClient->request('GET', '/storefront-api/category?' . $params);

        $response = $this->storefrontApiClient->getResponse();
        $content = json_decode($response->getContent(), true);
        $this->assertNotEmpty($content);
        $this->assertSame(1, $content['total']);

        $ids = array_column($content['data'], 'id');
        $this->assertContains($categoryC, $ids);
    }

    public function testSwagQLForListRoute()
    {
        $categoryC = Uuid::uuid4()->getHex();
        $categoryA = Uuid::uuid4()->getHex();
        $categoryB = Uuid::uuid4()->getHex();
        $categoryA2 = Uuid::uuid4()->getHex();

        $this->repository->create([
            ['id' => $categoryC, 'name' => 'C', 'active' => true],
            ['id' => $categoryA, 'name' => 'A', 'active' => true],
            ['id' => $categoryA2, 'name' => 'A', 'active' => false],
            ['id' => $categoryB, 'name' => 'B', 'active' => false],
        ], $this->context);

        $body = [
            'filter' => [
                [
                    'type' => 'terms',
                    'field' => 'category.id',
                    'value' => implode('|', [$categoryA, $categoryB]),
                ],
            ],
        ];

        $this->storefrontApiClient->request('POST', '/storefront-api/category', $body);

        $response = $this->storefrontApiClient->getResponse();
        $content = json_decode($response->getContent(), true);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(2, $content['total']);
        $ids = array_column($content['data'], 'id');
        $this->assertContains($categoryA, $ids);
        $this->assertContains($categoryB, $ids);

        $body = [
            'filter' => [
                [
                    'type' => 'term',
                    'field' => 'category.active',
                    'value' => true,
                ],
            ],
            'sort' => [
                ['field' => 'category.name'],
            ],
        ];

        $this->storefrontApiClient->request('POST', '/storefront-api/category', $body);
        $response = $this->storefrontApiClient->getResponse();
        $content = json_decode($response->getContent(), true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(2, $content['total']);
        $ids = array_column($content['data'], 'id');
        $this->assertSame([$categoryA, $categoryC], $ids);

        $body = [
            'filter' => [
                [
                    'type' => 'nested',
                    'operator' => 'OR',
                    'queries' => [
                        ['type' => 'term', 'field' => 'category.active', 'value' => true],
                        ['type' => 'term', 'field' => 'category.name', 'value' => 'B'],
                    ],
                ],
            ],
        ];

        $this->storefrontApiClient->request('POST', '/storefront-api/category', $body);
        $response = $this->storefrontApiClient->getResponse();
        $content = json_decode($response->getContent(), true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(3, $content['total']);
        $ids = array_column($content['data'], 'id');

        $this->assertContains($categoryA, $ids);
        $this->assertContains($categoryB, $ids);
        $this->assertContains($categoryC, $ids);

        $body = [
            'post-filter' => [
                ['type' => 'term', 'field' => 'category.active', 'value' => true],
            ],
            'aggregations' => [
                'category-names' => [
                    'value_count' => ['field' => 'category.name'],
                ],
            ],
        ];

        $this->storefrontApiClient->request('POST', '/storefront-api/category', $body);
        $response = $this->storefrontApiClient->getResponse();
        $content = json_decode($response->getContent(), true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(2, $content['total']);

        $ids = array_column($content['data'], 'id');

        $this->assertContains($categoryA, $ids);
        $this->assertContains($categoryC, $ids);

        $this->assertArrayHasKey('aggregations', $content);
        $this->assertArrayHasKey('category-names', $content['aggregations']);

        usort($content['aggregations']['category-names'], function ($a, $b) {
            return $a['key'] <=> $b['key'];
        });

        $this->assertEquals(
            [
                ['key' => 'A', 'count' => '2'],
                ['key' => 'B', 'count' => '1'],
                ['key' => 'C', 'count' => '1'],
            ],
            $content['aggregations']['category-names']
        );
    }

    public function testDetailWithNoneExistingCategory()
    {
        $id = Uuid::uuid4()->getHex();

        $this->storefrontApiClient->request('GET', '/storefront-api/category/' . $id);
        $response = $this->storefrontApiClient->getResponse();

        $this->assertSame(404, $response->getStatusCode());
        $content = json_decode($response->getContent(), true);

        $this->assertNotEmpty($content);
        $this->assertArrayHasKey('errors', $content);
        $this->assertEquals($content['errors'][0]['code'], CategoryNotFoundException::CODE);
        $this->assertEquals($content['errors'][0]['status'], 404);
        $this->assertStringMatchesFormat('Category %s not found', $content['errors'][0]['detail']);
    }
}
