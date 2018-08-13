<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\ORM\Search;

use Doctrine\DBAL\Connection;
use Ramsey\Uuid\Uuid;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\ORM\Exception\SearchRequestException;
use Shopware\Core\Framework\ORM\RepositoryInterface;
use Shopware\Core\Framework\ORM\Search\Criteria;
use Shopware\Core\Framework\ORM\Search\RequestCriteriaBuilder;
use Shopware\Core\Framework\ORM\Search\SearchBuilder;
use Shopware\Core\Framework\Test\Api\ApiTestCase;
use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\Request;

class SearchCriteriaBuilderTest extends ApiTestCase
{
    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var RepositoryInterface
     */
    private $manufacturerRepository;

    /**
     * @var string
     */
    private $url;

    protected function setUp()
    {
        parent::setUp();

        $this->manufacturerRepository = self::$container->get('product_manufacturer.repository');
        $this->connection = self::$container->get(Connection::class);
        $this->connection->beginTransaction();
        $this->url = '/api/v' . PlatformRequest::API_VERSION;

        $this->connection->executeUpdate('DELETE FROM product');
        $this->connection->executeUpdate('DELETE FROM product_manufacturer');
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
        for ($i = 0; $i < 35; ++$i) {
            $this->createManufacturer(['link' => 'test']);
        }

        // no count, equals to fetched entities
        $this->apiClient->request('GET', $this->url . '/product-manufacturer', ['fetch-count' => Criteria::FETCH_COUNT_NONE, 'filter' => ['product_manufacturer.link' => 'test'], 'limit' => 5]);
        static::assertSame(200, $this->apiClient->getResponse()->getStatusCode(), $this->apiClient->getResponse()->getContent());
        $content = \json_decode($this->apiClient->getResponse()->getContent(), true);

        static::assertEquals(5, $content['meta']['total'], print_r($content, true));

        // calculates all matching rows
        $this->apiClient->request('GET', $this->url . '/product-manufacturer', ['fetch-count' => Criteria::FETCH_COUNT_TOTAL, 'filter' => ['product_manufacturer.link' => 'test'], 'limit' => 5]);
        static::assertSame(200, $this->apiClient->getResponse()->getStatusCode());
        $content = json_decode($this->apiClient->getResponse()->getContent(), true);

        static::assertEquals(35, $content['meta']['total']);

        // returns the count of 5 next pages plus 1 if there are more
        $this->apiClient->request('GET', $this->url . '/product-manufacturer', ['fetch-count' => Criteria::FETCH_COUNT_NEXT_PAGES, 'filter' => ['product_manufacturer.link' => 'test'], 'limit' => 5]);
        static::assertSame(200, $this->apiClient->getResponse()->getStatusCode());
        $content = json_decode($this->apiClient->getResponse()->getContent(), true);

        static::assertEquals(31, $content['meta']['total']);
    }

    /**
     * SORTING
     */
    public function testSortingAscending(): void
    {
        $this->createManufacturer(['link' => 'a']);
        $this->createManufacturer(['link' => 'b']);
        $this->createManufacturer(['link' => 'c']);

        $this->apiClient->request('GET', $this->url . '/product-manufacturer', ['sort' => 'product_manufacturer.link']);
        static::assertSame(200, $this->apiClient->getResponse()->getStatusCode());
        $content = json_decode($this->apiClient->getResponse()->getContent(), true);

        static::assertEquals('a', $content['data'][0]['attributes']['link']);
        static::assertEquals('b', $content['data'][1]['attributes']['link']);
        static::assertEquals('c', $content['data'][2]['attributes']['link']);
    }

    public function testSortingDescending(): void
    {
        $this->createManufacturer(['link' => 'a']);
        $this->createManufacturer(['link' => 'b']);
        $this->createManufacturer(['link' => 'c']);

        $this->apiClient->request('GET', $this->url . '/product-manufacturer', ['sort' => '-product_manufacturer.link']);
        static::assertSame(200, $this->apiClient->getResponse()->getStatusCode());
        $content = json_decode($this->apiClient->getResponse()->getContent(), true);

        static::assertEquals('c', $content['data'][0]['attributes']['link']);
        static::assertEquals('b', $content['data'][1]['attributes']['link']);
        static::assertEquals('a', $content['data'][2]['attributes']['link']);
    }

    public function testMultipleSorting(): void
    {
        $manufacturer1 = $this->createManufacturer(['link' => 'a', 'description' => 'a']);
        $manufacturer2 = $this->createManufacturer(['link' => 'b', 'description' => 'a']);
        $manufacturer3 = $this->createManufacturer(['link' => 'b', 'description' => 'c']);

        /*
         * Sort by stock ASC, minStock ASC
         */
        $this->apiClient->request('GET', $this->url . '/product-manufacturer', ['sort' => 'product_manufacturer.link,product_manufacturer.description']);
        static::assertSame(200, $this->apiClient->getResponse()->getStatusCode());
        $content = json_decode($this->apiClient->getResponse()->getContent(), true);

        $expectedSort = [$manufacturer1, $manufacturer2, $manufacturer3];
        $actualSort = array_column($content['data'], 'id');

        static::assertEquals($expectedSort, $actualSort);

        /*
         * Sort by stock ASC, minStock DESC
         */
        $this->apiClient->request('GET', $this->url . '/product-manufacturer', ['sort' => 'product_manufacturer.link,-product_manufacturer.description']);
        static::assertSame(200, $this->apiClient->getResponse()->getStatusCode());
        $content = json_decode($this->apiClient->getResponse()->getContent(), true);

        $expectedSort = [$manufacturer1, $manufacturer3, $manufacturer2];
        $actualSort = array_column($content['data'], 'id');

        static::assertEquals($expectedSort, $actualSort);

        /*
         * Sort by stock DESC, minStock ASC
         */
        $this->apiClient->request('GET', $this->url . '/product-manufacturer', ['sort' => '-product_manufacturer.link,product_manufacturer.description']);
        static::assertSame(200, $this->apiClient->getResponse()->getStatusCode());
        $content = json_decode($this->apiClient->getResponse()->getContent(), true);

        $expectedSort = [$manufacturer2, $manufacturer3, $manufacturer1];
        $actualSort = array_column($content['data'], 'id');

        static::assertEquals($expectedSort, $actualSort);

        /*
         * Sort by stock DESC, minStock DESC
         */
        $this->apiClient->request('GET', $this->url . '/product-manufacturer', ['sort' => '-product_manufacturer.link,-product_manufacturer.description']);
        static::assertSame(200, $this->apiClient->getResponse()->getStatusCode());
        $content = json_decode($this->apiClient->getResponse()->getContent(), true);

        $expectedSort = [$manufacturer3, $manufacturer2, $manufacturer1];
        $actualSort = array_column($content['data'], 'id');

        static::assertEquals($expectedSort, $actualSort);
    }

    public function testSortingWithInvalidField(): void
    {
        $this->apiClient->request('GET', $this->url . '/product', ['sort' => 'product.unknown']);
        static::assertSame(400, $this->apiClient->getResponse()->getStatusCode());
        $content = json_decode($this->apiClient->getResponse()->getContent(), true);

        static::assertEquals('Field "unknown" in entity "product" was not found.', $content['errors'][0]['detail']);
    }

    public function testSortingWithEmptyField(): void
    {
        $this->apiClient->request('GET', $this->url . '/product', ['sort' => '']);
        static::assertSame(400, $this->apiClient->getResponse()->getStatusCode());
        $content = json_decode($this->apiClient->getResponse()->getContent(), true);

        static::assertEquals('A value for the sort parameter is required.', $content['errors'][0]['detail']);
        static::assertEquals('/sort', $content['errors'][0]['source']['pointer']);
    }

    /**
     * PAGING
     */
    public function testPage(): void
    {
        $link = 'testPage';
        $limit = 10;
        $pageCount = 2;
        $ids = $this->createData($link, $limit * $pageCount);

        $requestedPage = 2;
        $this->apiClient->request('GET', $this->url . '/product-manufacturer', ['page' => $requestedPage, 'limit' => $limit, 'filter' => ['product_manufacturer.link' => $link], 'sort' => 'product_manufacturer.id']);
        static::assertSame(200, $this->apiClient->getResponse()->getStatusCode());
        $content = json_decode($this->apiClient->getResponse()->getContent(), true);

        $expectedIds = array_slice($ids, $limit * ($requestedPage - 1), $limit);
        $actualIds = array_column($content['data'], 'id');

        static::assertEquals($expectedIds, $actualIds);
    }

    public function testPositiveNonExistentPage(): void
    {
        $link = 'testPositiveNonExistentPage';
        $limit = 10;
        $pageCount = 2;
        $this->createData($link, $limit * $pageCount);

        $requestedPage = 3;
        $this->apiClient->request('GET', $this->url . '/product-manufacturer', ['page' => $requestedPage, 'limit' => $limit, 'filter' => ['product_manufacturer.link' => $link], 'sort' => 'product_manufacturer.id']);
        static::assertSame(200, $this->apiClient->getResponse()->getStatusCode());
        $content = json_decode($this->apiClient->getResponse()->getContent(), true);

        $expectedIds = [];
        $actualIds = array_column($content['data'], 'id');

        static::assertEquals($expectedIds, $actualIds);
    }

    public function testSmallPage(): void
    {
        $link = 'testSmallPage';
        $limit = 10;
        $pageCount = 2;
        $ids = $this->createData($link, $limit * $pageCount + 1);

        $requestedPage = 3;
        $this->apiClient->request('GET', $this->url . '/product-manufacturer', ['page' => $requestedPage, 'limit' => $limit, 'filter' => ['product_manufacturer.link' => $link], 'sort' => 'product_manufacturer.id']);
        static::assertSame(200, $this->apiClient->getResponse()->getStatusCode());
        $content = json_decode($this->apiClient->getResponse()->getContent(), true);

        $expectedIds = array_slice($ids, $limit * ($requestedPage - 1), $limit);
        $actualIds = array_column($content['data'], 'id');

        static::assertEquals($expectedIds, $actualIds);
    }

    public function testNegativePage(): void
    {
        $this->apiClient->request('GET', $this->url . '/product', ['page' => -1]);
        static::assertSame(400, $this->apiClient->getResponse()->getStatusCode());
        $content = json_decode($this->apiClient->getResponse()->getContent(), true);

        static::assertEquals('The page parameter must be a positive integer. Given: -1', $content['errors'][0]['detail']);
        static::assertEquals('/page', $content['errors'][0]['source']['pointer']);
    }

    public function testNonIntegerPage(): void
    {
        $this->apiClient->request('GET', $this->url . '/product', ['page' => 'foo']);
        static::assertSame(400, $this->apiClient->getResponse()->getStatusCode());
        $content = json_decode($this->apiClient->getResponse()->getContent(), true);

        static::assertEquals('The page parameter must be a positive integer. Given: foo', $content['errors'][0]['detail']);
        static::assertEquals('/page', $content['errors'][0]['source']['pointer']);
    }

    public function testEmptyPage(): void
    {
        $this->apiClient->request('GET', $this->url . '/product', ['page' => '']);
        static::assertSame(400, $this->apiClient->getResponse()->getStatusCode());
        $content = json_decode($this->apiClient->getResponse()->getContent(), true);

        static::assertEquals('The page parameter must be a positive integer. Given: (empty)', $content['errors'][0]['detail']);
        static::assertEquals('/page', $content['errors'][0]['source']['pointer']);
    }

    /**
     * LIMIT
     */
    public function testLimit(): void
    {
        for ($i = 0; $i < 10; ++$i) {
            $this->createManufacturer(['link' => 'testLimit']);
        }

        $this->apiClient->request('GET', $this->url . '/product-manufacturer', ['limit' => 5, 'filter' => ['product_manufacturer.link' => 'testLimit'], 'sort' => 'product_manufacturer.id']);
        static::assertSame(200, $this->apiClient->getResponse()->getStatusCode());
        $content = json_decode($this->apiClient->getResponse()->getContent(), true);

        static::assertCount(5, $content['data']);
    }

    public function testLimitWithNumericString(): void
    {
        for ($i = 0; $i < 10; ++$i) {
            $this->createManufacturer(['link' => 'testLimitWithNumericString']);
        }

        $this->apiClient->request('GET', $this->url . '/product-manufacturer', ['limit' => '5', 'filter' => ['product_manufacturer.link' => 'testLimitWithNumericString'], 'sort' => 'product_manufacturer.id']);
        static::assertSame(200, $this->apiClient->getResponse()->getStatusCode());
        $content = json_decode($this->apiClient->getResponse()->getContent(), true);

        static::assertCount(5, $content['data']);
    }

    public function testNegativeLimit(): void
    {
        $this->apiClient->request('GET', $this->url . '/product', ['limit' => 0]);
        static::assertSame(400, $this->apiClient->getResponse()->getStatusCode());
        $content = json_decode($this->apiClient->getResponse()->getContent(), true);

        static::assertEquals('The limit parameter must be a positive integer greater or equals than 1. Given: 0', $content['errors'][0]['detail']);
        static::assertEquals('/limit', $content['errors'][0]['source']['pointer']);

        $this->apiClient->request('GET', $this->url . '/product', ['limit' => -1]);
        static::assertSame(400, $this->apiClient->getResponse()->getStatusCode());
        $content = json_decode($this->apiClient->getResponse()->getContent(), true);

        static::assertEquals('The limit parameter must be a positive integer greater or equals than 1. Given: -1', $content['errors'][0]['detail']);
        static::assertEquals('/limit', $content['errors'][0]['source']['pointer']);
    }

    public function testNonIntegerLimit(): void
    {
        $this->apiClient->request('GET', $this->url . '/product', ['limit' => 'foo']);
        static::assertSame(400, $this->apiClient->getResponse()->getStatusCode());
        $content = json_decode($this->apiClient->getResponse()->getContent(), true);

        static::assertEquals('The limit parameter must be a positive integer greater or equals than 1. Given: foo', $content['errors'][0]['detail']);
        static::assertEquals('/limit', $content['errors'][0]['source']['pointer']);
    }

    public function testEmptyLimit(): void
    {
        $this->apiClient->request('GET', $this->url . '/product', ['limit' => '']);
        static::assertSame(400, $this->apiClient->getResponse()->getStatusCode());
        $content = json_decode($this->apiClient->getResponse()->getContent(), true);

        static::assertEquals('The limit parameter must be a positive integer greater or equals than 1. Given: (empty)', $content['errors'][0]['detail']);
        static::assertEquals('/limit', $content['errors'][0]['source']['pointer']);
    }

    public function testLimitExceedingMaxLimit(): void
    {
        $max_limit = 50;
        $limit = $max_limit + 1;

        $params = [
            'limit' => $max_limit + 1,
        ];

        $got_error = false;
        try {
            $this->fakeHandleRequest($max_limit, [], $params);
        } catch (SearchRequestException $e) {
            $errors = $e->getErrors();
            $current = $errors->current();

            static::assertEquals('The limit must be lower than or equal to MAX_LIMIT(=' . $max_limit . '). Given: ' . $limit, $current['detail']);
            static::assertEquals('/limit', $current['source']['pointer']);
            $got_error = true;
        }
        static::assertTrue($got_error);
    }

    public function testDisallowedLimit(): void
    {
        $allowedLimits = [1, 10];
        $limit = 13;

        $params = [
            'limit' => $limit,
        ];

        $got_error = false;
        try {
            $this->fakeHandleRequest(0, $allowedLimits, $params);
        } catch (SearchRequestException $e) {
            $errors = $e->getErrors();
            $current = $errors->current();

            $message = sprintf('The limit must be one of the "allowed_limits" [%s]. Given: %s', implode(', ', $allowedLimits), $limit);
            static::assertEquals($message, $current['detail']);
            static::assertEquals('/limit', $current['source']['pointer']);

            $got_error = true;
        }
        static::assertTrue($got_error);
    }

    public function testMultipleErrorStack(): void
    {
        $query = [
            'limit' => '',
            'page' => '',
            'filter' => [
                ['type' => 'bar'],
                ['type' => 'term', 'field' => 'foo', 'value' => ''],
                ['type' => 'nested', 'queries' => [
                    ['type' => 'foo'],
                    ['type' => 'terms', 'value' => 'wusel'],
                ]],
            ],
        ];

        $this->apiClient->request('GET', $this->url . '/product', $query);
        static::assertSame(400, $this->apiClient->getResponse()->getStatusCode());
        $content = json_decode($this->apiClient->getResponse()->getContent(), true);

        static::assertCount(6, $content['errors'], print_r($content['errors'], true));
        static::assertEquals('/limit', $content['errors'][0]['source']['pointer']);
        static::assertEquals('/page', $content['errors'][1]['source']['pointer']);
        static::assertEquals('/filter/0/type', $content['errors'][2]['source']['pointer']);
        static::assertEquals('/filter/1/value', $content['errors'][3]['source']['pointer']);
        static::assertEquals('/filter/2/queries/0/type', $content['errors'][4]['source']['pointer']);
        static::assertEquals('/filter/2/queries/1/field', $content['errors'][5]['source']['pointer']);
    }

    private function fakeHandleRequest($maxLimit = 0, $allowedLimits = [], $params = [])
    {
        $searchBuilder = $this::$container->get(SearchBuilder::class);
        $requestBuilder = new RequestCriteriaBuilder($searchBuilder, $maxLimit, $allowedLimits);
        $context = Context::createDefaultContext(Defaults::TENANT_ID);
        $definition = 'Shopware\Core\Content\Product\ProductDefinition';

        $request = new Request($params);

        return $requestBuilder->handleRequest($request, new Criteria(), $definition, $context);
    }

    private function createData(string $link, int $count): array
    {
        $ids = [];
        for ($i = 0; $i < $count; ++$i) {
            $ids[] = $this->createManufacturer(['link' => $link]);
        }
        sort($ids);

        return $ids;
    }

    private function createManufacturer(array $parameters = []): string
    {
        $id = Uuid::uuid4()->getHex();

        $defaults = ['id' => $id, 'name' => 'Test'];

        $parameters = array_merge($defaults, $parameters);

        $this->manufacturerRepository->create([$parameters], Context::createDefaultContext(Defaults::TENANT_ID));

        return $id;
    }
}
