<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Category\SalesChannel;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelFunctionalTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;

class SalesChannelCategoryControllerTest extends TestCase
{
    use SalesChannelFunctionalTestBehaviour;

    /**
     * @var EntityRepositoryInterface
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

    protected function setUp(): void
    {
        $this->connection = $this->getContainer()->get(Connection::class);
        $this->repository = $this->getContainer()->get('category.repository');
        $this->context = Context::createDefaultContext();
    }

    public function testCategoryListRoute(): void
    {
        $id = Uuid::randomHex();

        $this->repository->create([
            ['id' => $id, 'name' => 'Test category', 'active' => true],
        ], $this->context);

        $this->getSalesChannelBrowser()->request('GET', '/sales-channel-api/v' . PlatformRequest::API_VERSION . '/category');

        $response = $this->getSalesChannelBrowser()->getResponse();

        static::assertSame(200, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);

        static::assertNotEmpty($content);
        static::assertArrayHasKey('data', $content);
        static::assertArrayHasKey('total', $content);
        static::assertGreaterThanOrEqual(1, $content['total']);
    }

    public function testCategoryDetailRoute(): void
    {
        $id = Uuid::randomHex();

        $this->repository->create([
            ['id' => $id, 'name' => 'Test category', 'active' => true],
        ], $this->context);

        $this->getSalesChannelBrowser()->request('GET', '/sales-channel-api/v' . PlatformRequest::API_VERSION . '/category/' . $id);

        $response = $this->getSalesChannelBrowser()->getResponse();
        $content = json_decode($response->getContent(), true);

        static::assertSame(200, $response->getStatusCode(), print_r($content, true));

        static::assertNotEmpty($content);
        static::assertArrayHasKey('data', $content);
        static::assertSame('Test category', $content['data']['name']);
    }

    public function testSortingOnListRoute(): void
    {
        $categoryC = Uuid::randomHex();
        $categoryA = Uuid::randomHex();
        $categoryB = Uuid::randomHex();

        $this->repository->create([
            ['id' => $categoryC, 'name' => 'Category C', 'active' => true],
            ['id' => $categoryA, 'name' => 'Category A', 'active' => true],
            ['id' => $categoryB, 'name' => 'Category B', 'active' => true],
        ], $this->context);

        $params = [
            'sort' => 'name',
            'filter' => [
                ['type' => 'equalsAny', 'field' => 'category.id', 'value' => [$categoryA, $categoryB, $categoryC]],
            ],
        ];

        $this->getSalesChannelBrowser()->request('GET', '/sales-channel-api/v' . PlatformRequest::API_VERSION . '/category', $params);

        $response = $this->getSalesChannelBrowser()->getResponse();
        $content = json_decode($response->getContent(), true);
        static::assertNotEmpty($content);
        $ids = array_column($content['data'], 'id');
        static::assertSame([$categoryA, $categoryB, $categoryC], $ids);

        $params['sort'] = '-name';
        $this->getSalesChannelBrowser()->request('GET', '/sales-channel-api/v' . PlatformRequest::API_VERSION . '/category', $params);
        $response = $this->getSalesChannelBrowser()->getResponse();
        $content = json_decode($response->getContent(), true);
        static::assertNotEmpty($content);
        $ids = array_column($content['data'], 'id');

        static::assertSame([$categoryC, $categoryB, $categoryA], $ids);
    }

    public function testTermOnListRoute(): void
    {
        $categoryC = Uuid::randomHex();
        $categoryA = Uuid::randomHex();
        $categoryB = Uuid::randomHex();

        $this->repository->create([
            ['id' => $categoryC, 'active' => true, 'name' => 'Matching name'],
            ['id' => $categoryA, 'active' => true, 'name' => 'Not'],
            ['id' => $categoryB, 'active' => true, 'name' => 'Matching name'],
        ], $this->context);

        $this->getSalesChannelBrowser()->request('GET', '/sales-channel-api/v' . PlatformRequest::API_VERSION . '/category', ['term' => 'Matching']);

        $response = $this->getSalesChannelBrowser()->getResponse();
        $content = json_decode($response->getContent(), true);

        static::assertNotEmpty($content);
        static::assertSame(2, $content['total']);

        $ids = array_column($content['data'], 'id');
        static::assertContains($categoryC, $ids);
        static::assertContains($categoryB, $ids);
    }

    public function testFilterOnListRoute(): void
    {
        $categoryC = Uuid::randomHex();
        $categoryA = Uuid::randomHex();
        $categoryB = Uuid::randomHex();

        $this->repository->create([
            ['id' => $categoryC, 'name' => 'Matching name', 'active' => true],
            ['id' => $categoryA, 'name' => 'Not', 'active' => false],
            ['id' => $categoryB, 'name' => 'Matching name', 'active' => false],
        ], $this->context);

        $params = [
            'filter' => [
                'name' => 'Matching name',
                'active' => true,
            ],
        ];

        $this->getSalesChannelBrowser()->request('GET', '/sales-channel-api/v' . PlatformRequest::API_VERSION . '/category', $params);

        $response = $this->getSalesChannelBrowser()->getResponse();
        $content = json_decode($response->getContent(), true);
        static::assertNotEmpty($content);
        static::assertSame(1, $content['total']);

        $ids = array_column($content['data'], 'id');
        static::assertContains($categoryC, $ids);
    }

    public function testSwagQLForListRoute(): void
    {
        $categoryC = Uuid::randomHex();
        $categoryA = Uuid::randomHex();
        $categoryB = Uuid::randomHex();
        $categoryA2 = Uuid::randomHex();

        $this->repository->create([
            ['id' => $categoryC, 'name' => 'C', 'active' => true],
            ['id' => $categoryA, 'name' => 'A', 'active' => true],
            ['id' => $categoryA2, 'name' => 'A', 'active' => true, 'displayNestedProducts' => false],
            ['id' => $categoryB, 'name' => 'B', 'active' => true, 'displayNestedProducts' => false],
        ], $this->context);

        $body = [
            'filter' => [
                [
                    'type' => 'equalsAny',
                    'field' => 'category.id',
                    'value' => implode('|', [$categoryA, $categoryB]),
                ],
            ],
        ];

        $this->getSalesChannelBrowser()->request('POST', '/sales-channel-api/v' . PlatformRequest::API_VERSION . '/category', $body);

        $response = $this->getSalesChannelBrowser()->getResponse();
        $content = json_decode($response->getContent(), true);
        static::assertSame(200, $response->getStatusCode());
        static::assertSame(2, $content['total']);
        $ids = array_column($content['data'], 'id');
        static::assertContains($categoryA, $ids);
        static::assertContains($categoryB, $ids);

        $body = [
            'filter' => [
                [
                    'type' => 'equals',
                    'field' => 'category.displayNestedProducts',
                    'value' => false,
                ],
            ],
            'sort' => [
                ['field' => 'category.name'],
            ],
        ];

        $this->getSalesChannelBrowser()->request('POST', '/sales-channel-api/v' . PlatformRequest::API_VERSION . '/category', $body);
        $response = $this->getSalesChannelBrowser()->getResponse();
        $content = json_decode($response->getContent(), true);

        static::assertSame(200, $response->getStatusCode());
        static::assertSame(2, $content['total']);
        $ids = array_column($content['data'], 'id');
        static::assertSame([$categoryA2, $categoryB], $ids);

        $body = [
            'filter' => [
                [
                    'type' => 'multi',
                    'operator' => 'OR',
                    'queries' => [
                        ['type' => 'equals', 'field' => 'category.displayNestedProducts', 'value' => false],
                        ['type' => 'equals', 'field' => 'category.name', 'value' => 'B'],
                    ],
                ],
            ],
        ];

        $this->getSalesChannelBrowser()->request('POST', '/sales-channel-api/v' . PlatformRequest::API_VERSION . '/category', $body);
        $response = $this->getSalesChannelBrowser()->getResponse();
        $content = json_decode($response->getContent(), true);

        static::assertSame(200, $response->getStatusCode());
        static::assertSame(2, $content['total']);
        $ids = array_column($content['data'], 'id');

        static::assertContains($categoryA2, $ids);
        static::assertContains($categoryB, $ids);

        $body = [
            'post-filter' => [
                ['type' => 'equals', 'field' => 'category.displayNestedProducts', 'value' => false],
            ],
            'aggregations' => [
                [
                    'name' => 'category-names',
                    'type' => 'terms',
                    'field' => 'category.name',
                ],
            ],
        ];

        $this->getSalesChannelBrowser()->request('POST', '/sales-channel-api/v' . PlatformRequest::API_VERSION . '/category', $body);
        $response = $this->getSalesChannelBrowser()->getResponse();
        $content = json_decode($response->getContent(), true);

        static::assertSame(200, $response->getStatusCode());
        static::assertSame(2, $content['total']);

        $ids = array_column($content['data'], 'id');

        static::assertContains($categoryA2, $ids);
        static::assertContains($categoryB, $ids);

        static::assertArrayHasKey('aggregations', $content);
        static::assertArrayHasKey('category-names', $content['aggregations']);

        usort($content['aggregations']['category-names']['buckets'], function ($a, $b) {
            return $a['key'] <=> $b['key'];
        });

        $values = $content['aggregations']['category-names']['buckets'];

        static::assertContains(['key' => 'A', 'count' => '2', 'apiAlias' => 'aggregation_bucket'], $values);
        static::assertContains(['key' => 'B', 'count' => '1', 'apiAlias' => 'aggregation_bucket'], $values);
        static::assertContains(['key' => 'C', 'count' => '1', 'apiAlias' => 'aggregation_bucket'], $values);
    }

    public function testDetailWithNoneExistingCategory(): void
    {
        $id = Uuid::randomHex();

        $this->getSalesChannelBrowser()->request('GET', '/sales-channel-api/v' . PlatformRequest::API_VERSION . '/category/' . $id);
        $response = $this->getSalesChannelBrowser()->getResponse();

        static::assertSame(404, $response->getStatusCode());
        $content = json_decode($response->getContent(), true);

        static::assertNotEmpty($content);
        static::assertArrayHasKey('errors', $content);
        static::assertEquals('FRAMEWORK__RESOURCE_NOT_FOUND', $content['errors'][0]['code']);
        static::assertEquals(404, $content['errors'][0]['status']);
        static::assertStringMatchesFormat('The category resource with the following primary key was not found: id(%s)', $content['errors'][0]['detail']);
    }
}
