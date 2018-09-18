<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Category\Storefront;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\Exception\CategoryNotFoundException;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\ORM\RepositoryInterface;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Core\Framework\Test\TestCaseBase\StorefrontFunctionalTestBehaviour;

class StorefrontCategoryControllerTest extends TestCase
{
    use StorefrontFunctionalTestBehaviour;

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
        $this->connection = $this->getContainer()->get(Connection::class);
        $this->repository = $this->getContainer()->get('category.repository');
        $this->context = Context::createDefaultContext(Defaults::TENANT_ID);
    }

    public function testCategoryListRoute(): void
    {
        $id = Uuid::uuid4()->getHex();

        $this->repository->create([
            ['id' => $id, 'name' => 'Test category'],
        ], $this->context);

        $this->getStorefrontClient()->request('GET', '/storefront-api/category');

        $response = $this->getStorefrontClient()->getResponse();

        static::assertSame(200, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);

        static::assertNotEmpty($content);
        static::assertArrayHasKey('data', $content);
        static::assertArrayHasKey('total', $content);
        static::assertGreaterThanOrEqual(1, $content['total']);
    }

    public function testCategoryDetailRoute(): void
    {
        $id = Uuid::uuid4()->getHex();

        $this->repository->create([
            ['id' => $id, 'name' => 'Test category'],
        ], $this->context);

        $this->getStorefrontClient()->request('GET', '/storefront-api/category/' . $id);

        $response = $this->getStorefrontClient()->getResponse();

        static::assertSame(200, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);

        static::assertNotEmpty($content);
        static::assertArrayHasKey('data', $content);
        static::assertSame('Test category', $content['data']['name']);
    }

    public function testSortingOnListRoute(): void
    {
        $categoryC = Uuid::uuid4()->getHex();
        $categoryA = Uuid::uuid4()->getHex();
        $categoryB = Uuid::uuid4()->getHex();

        $filterId = Uuid::uuid4()->getHex();
        $this->repository->create([
            ['id' => $categoryC, 'name' => 'Category C', 'template' => $filterId],
            ['id' => $categoryA, 'name' => 'Category A', 'template' => $filterId],
            ['id' => $categoryB, 'name' => 'Category B', 'template' => $filterId],
        ], $this->context);

        $params = ['sort' => 'name', 'filter' => ['template' => $filterId]];
        $this->getStorefrontClient()->request('GET', '/storefront-api/category', $params);

        $response = $this->getStorefrontClient()->getResponse();
        $content = json_decode($response->getContent(), true);
        static::assertNotEmpty($content);
        $ids = array_column($content['data'], 'id');
        static::assertSame([$categoryA, $categoryB, $categoryC], $ids);

        $params['sort'] = '-name';
        $this->getStorefrontClient()->request('GET', '/storefront-api/category', $params);
        $response = $this->getStorefrontClient()->getResponse();
        $content = json_decode($response->getContent(), true);
        static::assertNotEmpty($content);
        $ids = array_column($content['data'], 'id');

        static::assertSame([$categoryC, $categoryB, $categoryA], $ids);
    }

    public function testTermOnListRoute(): void
    {
        $categoryC = Uuid::uuid4()->getHex();
        $categoryA = Uuid::uuid4()->getHex();
        $categoryB = Uuid::uuid4()->getHex();

        $filterId = Uuid::uuid4()->getHex();
        $this->repository->create([
            ['id' => $categoryC, 'name' => 'Matching name', 'template' => $filterId],
            ['id' => $categoryA, 'name' => 'Not', 'template' => $filterId],
            ['id' => $categoryB, 'name' => 'Matching name', 'template' => $filterId],
        ], $this->context);

        $this->getStorefrontClient()->request('GET', '/storefront-api/category', ['term' => 'Matching', 'filter' => ['template' => $filterId]]);

        $response = $this->getStorefrontClient()->getResponse();
        $content = json_decode($response->getContent(), true);

        static::assertNotEmpty($content);
        static::assertSame(2, $content['total']);

        $ids = array_column($content['data'], 'id');
        static::assertContains($categoryC, $ids);
        static::assertContains($categoryB, $ids);
    }

    public function testFilterOnListRoute(): void
    {
        $categoryC = Uuid::uuid4()->getHex();
        $categoryA = Uuid::uuid4()->getHex();
        $categoryB = Uuid::uuid4()->getHex();

        $filterId = Uuid::uuid4()->getHex();
        $this->repository->create([
            ['id' => $categoryC, 'name' => 'Matching name', 'active' => true, 'template' => $filterId],
            ['id' => $categoryA, 'name' => 'Not', 'active' => false, 'template' => $filterId],
            ['id' => $categoryB, 'name' => 'Matching name', 'active' => false, 'template' => $filterId],
        ], $this->context);

        $params = [
            'filter' => [
                'name' => 'Matching name',
                'active' => true,
                'template' => $filterId,
            ],
        ];

        $this->getStorefrontClient()->request('GET', '/storefront-api/category', $params);

        $response = $this->getStorefrontClient()->getResponse();
        $content = json_decode($response->getContent(), true);
        static::assertNotEmpty($content);
        static::assertSame(1, $content['total']);

        $ids = array_column($content['data'], 'id');
        static::assertContains($categoryC, $ids);
    }

    public function testSwagQLForListRoute(): void
    {
        $categoryC = Uuid::uuid4()->getHex();
        $categoryA = Uuid::uuid4()->getHex();
        $categoryB = Uuid::uuid4()->getHex();
        $categoryA2 = Uuid::uuid4()->getHex();

        $filterId = Uuid::uuid4()->getHex();
        $this->repository->create([
            ['id' => $categoryC, 'name' => 'C', 'active' => true, 'template' => $filterId],
            ['id' => $categoryA, 'name' => 'A', 'active' => true, 'template' => $filterId],
            ['id' => $categoryA2, 'name' => 'A', 'active' => false, 'template' => $filterId],
            ['id' => $categoryB, 'name' => 'B', 'active' => false, 'template' => $filterId],
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

        $this->getStorefrontClient()->request('POST', '/storefront-api/category', $body);

        $response = $this->getStorefrontClient()->getResponse();
        $content = json_decode($response->getContent(), true);
        static::assertSame(200, $response->getStatusCode());
        static::assertSame(2, $content['total']);
        $ids = array_column($content['data'], 'id');
        static::assertContains($categoryA, $ids);
        static::assertContains($categoryB, $ids);

        $body = [
            'filter' => [
                [
                    'type' => 'term',
                    'field' => 'category.active',
                    'value' => true,
                ],
                [
                    'type' => 'term',
                    'field' => 'category.template',
                    'value' => $filterId,
                ],
            ],
            'sort' => [
                ['field' => 'category.name'],
            ],
        ];

        $this->getStorefrontClient()->request('POST', '/storefront-api/category', $body);
        $response = $this->getStorefrontClient()->getResponse();
        $content = json_decode($response->getContent(), true);

        static::assertSame(200, $response->getStatusCode());
        static::assertSame(2, $content['total']);
        $ids = array_column($content['data'], 'id');
        static::assertSame([$categoryA, $categoryC], $ids);

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
                [
                    'type' => 'term',
                    'field' => 'category.template',
                    'value' => $filterId,
                ],
            ],
        ];

        $this->getStorefrontClient()->request('POST', '/storefront-api/category', $body);
        $response = $this->getStorefrontClient()->getResponse();
        $content = json_decode($response->getContent(), true);

        static::assertSame(200, $response->getStatusCode());
        static::assertSame(3, $content['total']);
        $ids = array_column($content['data'], 'id');

        static::assertContains($categoryA, $ids);
        static::assertContains($categoryB, $ids);
        static::assertContains($categoryC, $ids);

        $body = [
            'filter' => [
                ['type' => 'term', 'field' => 'category.template', 'value' => $filterId],
            ],
            'post-filter' => [
                ['type' => 'term', 'field' => 'category.active', 'value' => true],
            ],
            'aggregations' => [
                'category-names' => [
                    'value_count' => ['field' => 'category.name'],
                ],
            ],
        ];

        $this->getStorefrontClient()->request('POST', '/storefront-api/category', $body);
        $response = $this->getStorefrontClient()->getResponse();
        $content = json_decode($response->getContent(), true);

        static::assertSame(200, $response->getStatusCode());
        static::assertSame(2, $content['total']);

        $ids = array_column($content['data'], 'id');

        static::assertContains($categoryA, $ids);
        static::assertContains($categoryC, $ids);

        static::assertArrayHasKey('aggregations', $content);
        static::assertArrayHasKey('category-names', $content['aggregations']);

        usort($content['aggregations']['category-names'], function ($a, $b) {
            return $a['key'] <=> $b['key'];
        });

        static::assertEquals(
            [
                ['key' => 'A', 'count' => '2'],
                ['key' => 'B', 'count' => '1'],
                ['key' => 'C', 'count' => '1'],
            ],
            $content['aggregations']['category-names']
        );
    }

    public function testDetailWithNoneExistingCategory(): void
    {
        $id = Uuid::uuid4()->getHex();

        $this->getStorefrontClient()->request('GET', '/storefront-api/category/' . $id);
        $response = $this->getStorefrontClient()->getResponse();

        static::assertSame(404, $response->getStatusCode());
        $content = json_decode($response->getContent(), true);

        static::assertNotEmpty($content);
        static::assertArrayHasKey('errors', $content);
        static::assertEquals($content['errors'][0]['code'], CategoryNotFoundException::CODE);
        static::assertEquals($content['errors'][0]['status'], 404);
        static::assertStringMatchesFormat('Category %s not found', $content['errors'][0]['detail']);
    }
}
