<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Api\Controller;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\AdminApiTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\BasicTestDataBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\FilesystemBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\Response;

class ApiControllerTest extends TestCase
{
    use KernelTestBehaviour,
        FilesystemBehaviour,
        BasicTestDataBehaviour,
        AdminApiTestBehaviour;

    /**
     * @var Connection
     */
    private $connection;

    protected function setUp(): void
    {
        $dropStatement = <<<EOF
DROP TABLE IF EXISTS `named`;
DROP TABLE IF EXISTS `named_optional_group`;
EOF;

        $namedOptionalGroupStatement = <<<EOF
CREATE TABLE `named_optional_group` (
    `id` binary(16) NOT NULL,
    `name` varchar(255) NOT NULL,
    PRIMARY KEY `id` (`id`)
);
EOF;

        $namedStatement = <<<EOF
CREATE TABLE `named` (
    `id` binary(16) NOT NULL,
    `name` varchar(255) NOT NULL,    
    `optional_group_id` varbinary(16) NULL,    
    PRIMARY KEY `id` (`id`),  
    CONSTRAINT `fk` FOREIGN KEY (`optional_group_id`) REFERENCES `named_optional_group` (`id`) ON DELETE SET NULL
);
EOF;
        $this->connection = $this->getContainer()->get(Connection::class);
        $this->connection->executeUpdate($dropStatement);
        $this->connection->executeUpdate($namedOptionalGroupStatement);
        $this->connection->executeUpdate($namedStatement);

        $this->connection->beginTransaction();
    }

    public function tearDown(): void
    {
        $this->connection->rollBack();

        $this->connection->executeUpdate('DROP TABLE IF EXISTS `named`');
        $this->connection->executeUpdate('DROP TABLE IF EXISTS `named_optional_group`');

        parent::tearDown();
    }

    public function testInsert(): void
    {
        $id = Uuid::randomHex();

        $data = [
            'id' => $id,
            'productNumber' => Uuid::randomHex(),
            'stock' => 1,
            'name' => $id,
            'tax' => ['name' => 'test', 'taxRate' => 10],
            'manufacturer' => ['name' => 'test'],
            'price' => ['gross' => 50, 'net' => 25, 'linked' => false],
        ];

        $this->getClient()->request('POST', '/api/v' . PlatformRequest::API_VERSION . '/product', $data);
        $response = $this->getClient()->getResponse();

        /* @var Response $response */
        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode(), $response->getContent());

        static::assertNotEmpty($response->headers->get('Location'));
        static::assertEquals('http://localhost/api/v' . PlatformRequest::API_VERSION . '/product/' . $id, $response->headers->get('Location'));

        $this->getClient()->request('GET', '/api/v' . PlatformRequest::API_VERSION . '/product/' . $id);
        static::assertSame(Response::HTTP_OK, $this->getClient()->getResponse()->getStatusCode());
    }

    public function testOneToManyInsert(): void
    {
        $id = Uuid::randomHex();

        $data = ['id' => $id, 'name' => $id];

        $this->getClient()->request('POST', '/api/v' . PlatformRequest::API_VERSION . '/country', $data);
        $response = $this->getClient()->getResponse();
        static::assertSame(Response::HTTP_NO_CONTENT, $this->getClient()->getResponse()->getStatusCode(), $this->getClient()->getResponse()->getContent());
        static::assertNotEmpty($response->headers->get('Location'));
        static::assertEquals('http://localhost/api/v' . PlatformRequest::API_VERSION . '/country/' . $id, $response->headers->get('Location'));

        $this->getClient()->request('GET', '/api/v' . PlatformRequest::API_VERSION . '/country/' . $id);
        static::assertSame(Response::HTTP_OK, $this->getClient()->getResponse()->getStatusCode(), $this->getClient()->getResponse()->getContent());

        $data = [
            'id' => $id,
            'name' => 'test_state',
            'shortCode' => 'test',
        ];

        $this->getClient()->request('POST', '/api/v' . PlatformRequest::API_VERSION . '/country/' . $id . '/states/', $data);
        $response = $this->getClient()->getResponse();
        static::assertSame(Response::HTTP_NO_CONTENT, $this->getClient()->getResponse()->getStatusCode(), $this->getClient()->getResponse()->getContent());
        static::assertNotEmpty($response->headers->get('Location'));
        static::assertEquals('http://localhost/api/v' . PlatformRequest::API_VERSION . '/country-state/' . $id, $response->headers->get('Location'));

        $this->getClient()->request('GET', '/api/v' . PlatformRequest::API_VERSION . '/country/' . $id . '/states/');
        $responseData = json_decode($this->getClient()->getResponse()->getContent(), true);
        static::assertSame(Response::HTTP_OK, $this->getClient()->getResponse()->getStatusCode());

        static::assertArrayHasKey('data', $responseData);
        static::assertCount(1, $responseData['data'], sprintf('Expected country %s has only one state', $id));

        static::assertArrayHasKey('data', $responseData);
        static::assertEquals(1, $responseData['meta']['total']);

        static::assertSame($data['name'], $responseData['data'][0]['attributes']['name']);
        static::assertSame($data['shortCode'], $responseData['data'][0]['attributes']['shortCode']);
    }

    public function testManyToOneInsert(): void
    {
        $id = Uuid::randomHex();
        $manufacturer = Uuid::randomHex();

        $data = [
            'id' => $id,
            'productNumber' => Uuid::randomHex(),
            'stock' => 1,
            'name' => $id,
            'tax' => ['name' => 'test', 'taxRate' => 10],
            'manufacturer' => ['name' => 'test'],
            'price' => ['gross' => 50, 'net' => 25, 'linked' => false],
        ];

        $this->getClient()->request('POST', '/api/v' . PlatformRequest::API_VERSION . '/product', $data);
        $response = $this->getClient()->getResponse();
        static::assertSame(Response::HTTP_NO_CONTENT, $this->getClient()->getResponse()->getStatusCode(), 'Create product failed id:' . $id);
        static::assertNotEmpty($response->headers->get('Location'));
        static::assertEquals('http://localhost/api/v' . PlatformRequest::API_VERSION . '/product/' . $id, $response->headers->get('Location'));

        $data = [
            'id' => $manufacturer,
            'name' => 'Manufacturer - 1',
            'link' => 'https://www.shopware.com',
        ];

        $this->getClient()->request('POST', '/api/v' . PlatformRequest::API_VERSION . '/product/' . $id . '/manufacturer', $data);
        $response = $this->getClient()->getResponse();
        static::assertSame(Response::HTTP_NO_CONTENT, $this->getClient()->getResponse()->getStatusCode(), 'Create manufacturer over product failed id:' . $id . "\n" . $this->getClient()->getResponse()->getContent());
        static::assertNotEmpty($response->headers->get('Location'));
        static::assertEquals('http://localhost/api/v' . PlatformRequest::API_VERSION . '/product-manufacturer/' . $manufacturer, $response->headers->get('Location'));

        $this->getClient()->request('GET', '/api/v' . PlatformRequest::API_VERSION . '/product/' . $id . '/manufacturer');
        $responseData = json_decode($this->getClient()->getResponse()->getContent(), true);
        static::assertSame(Response::HTTP_OK, $this->getClient()->getResponse()->getStatusCode(), 'Read manufacturer of product failed id: ' . $id . PHP_EOL . $this->getClient()->getResponse()->getContent());

        static::assertArrayHasKey('data', $responseData, $this->getClient()->getResponse()->getContent());
        static::assertArrayHasKey(0, $responseData['data'], $this->getClient()->getResponse()->getContent());
        static::assertSame($data['name'], $responseData['data'][0]['attributes']['name']);
        static::assertSame($data['link'], $responseData['data'][0]['attributes']['link']);
        static::assertSame($data['id'], $responseData['data'][0]['id']);
    }

    public function testManyToManyInsert(): void
    {
        $id = Uuid::randomHex();

        $data = [
            'id' => $id,
            'productNumber' => Uuid::randomHex(),
            'stock' => 1,
            'name' => $id,
            'tax' => ['name' => 'test', 'taxRate' => 10],
            'manufacturer' => ['name' => 'test'],
            'price' => ['gross' => 50, 'net' => 25, 'linked' => false],
        ];

        $this->getClient()->request('POST', '/api/v' . PlatformRequest::API_VERSION . '/product', $data);
        $response = $this->getClient()->getResponse();
        static::assertSame(Response::HTTP_NO_CONTENT, $this->getClient()->getResponse()->getStatusCode(), $this->getClient()->getResponse()->getContent());
        static::assertNotEmpty($response->headers->get('Location'));
        static::assertEquals('http://localhost/api/v' . PlatformRequest::API_VERSION . '/product/' . $id, $response->headers->get('Location'));

        $data = [
            'id' => $id,
            'name' => 'Category - 1',
        ];

        $this->getClient()->request('POST', '/api/v' . PlatformRequest::API_VERSION . '/product/' . $id . '/categories/', $data);
        $response = $this->getClient()->getResponse();
        static::assertSame(Response::HTTP_NO_CONTENT, $this->getClient()->getResponse()->getStatusCode(), $this->getClient()->getResponse()->getContent());
        static::assertNotEmpty($response->headers->get('Location'));
        static::assertEquals('http://localhost/api/v1/category/' . $id, $response->headers->get('Location'));

        $this->getClient()->request('GET', '/api/v' . PlatformRequest::API_VERSION . '/product/' . $id . '/categories/');
        $responseData = json_decode($this->getClient()->getResponse()->getContent(), true);
        static::assertSame(Response::HTTP_OK, $this->getClient()->getResponse()->getStatusCode());

        static::assertArrayHasKey('data', $responseData);
        static::assertCount(1, $responseData['data']);
        static::assertArrayHasKey('attributes', $responseData['data'][0]);
        static::assertArrayHasKey('name', $responseData['data'][0]['attributes'], print_r($responseData, true));
        static::assertSame($data['name'], $responseData['data'][0]['attributes']['name']);
        static::assertSame($data['id'], $responseData['data'][0]['id']);
    }

    public function testDelete(): void
    {
        $id = Uuid::randomHex();

        $data = [
            'id' => $id,
            'productNumber' => Uuid::randomHex(),
            'stock' => 1,
            'name' => $id,
            'tax' => ['name' => 'test', 'taxRate' => 10],
            'manufacturer' => ['name' => 'test'],
            'price' => ['gross' => 50, 'net' => 25, 'linked' => false],
        ];

        $this->getClient()->request('POST', '/api/v' . PlatformRequest::API_VERSION . '/product', $data);
        $response = $this->getClient()->getResponse();
        static::assertSame(Response::HTTP_NO_CONTENT, $this->getClient()->getResponse()->getStatusCode(), $this->getClient()->getResponse()->getContent());
        static::assertNotEmpty($response->headers->get('Location'));
        static::assertEquals('http://localhost/api/v' . PlatformRequest::API_VERSION . '/product/' . $id, $response->headers->get('Location'));

        $this->assertEntityExists($this->getClient(), 'product', $id);

        $this->getClient()->request('DELETE', '/api/v' . PlatformRequest::API_VERSION . '/product/' . $id);
        static::assertSame(Response::HTTP_NO_CONTENT, $this->getClient()->getResponse()->getStatusCode(), $this->getClient()->getResponse()->getContent());

        $this->assertEntityNotExists($this->getClient(), 'product', $id);
    }

    public function testDeleteOneToMany(): void
    {
        $id = Uuid::randomHex();
        $stateId = Uuid::randomHex();

        $data = [
            'id' => $id,
            'name' => $id,
            'states' => [
                ['id' => $stateId, 'shortCode' => 'test', 'name' => 'test'],
            ],
        ];

        $this->getClient()->request('POST', '/api/v' . PlatformRequest::API_VERSION . '/country', $data);
        $response = $this->getClient()->getResponse();
        static::assertSame(Response::HTTP_NO_CONTENT, $this->getClient()->getResponse()->getStatusCode(), $this->getClient()->getResponse()->getContent());
        static::assertNotEmpty($response->headers->get('Location'));
        static::assertEquals('http://localhost/api/v' . PlatformRequest::API_VERSION . '/country/' . $id, $response->headers->get('Location'));

        $this->assertEntityExists($this->getClient(), 'country', $id);
        $this->assertEntityExists($this->getClient(), 'country-state', $stateId);

        $this->getClient()->request('DELETE', '/api/v' . PlatformRequest::API_VERSION . '/country/' . $id . '/states/' . $stateId, $data);
        static::assertSame(Response::HTTP_NO_CONTENT, $this->getClient()->getResponse()->getStatusCode(), $this->getClient()->getResponse()->getContent());

        $this->assertEntityExists($this->getClient(), 'country', $id);
        $this->assertEntityNotExists($this->getClient(), 'country-state', $stateId);
    }

    public function testDeleteManyToOne(): void
    {
        $id = Uuid::randomHex();
        $groupId = Uuid::randomHex();

        $data = [
            'id' => $id,
            'name' => 'Test product',
            'optionalGroup' => [
                'id' => $groupId,
                'name' => 'Gramm',
            ],
        ];
        $this->getClient()->request('POST', '/api/v' . PlatformRequest::API_VERSION . '/named', $data);
        $response = $this->getClient()->getResponse();
        static::assertSame(Response::HTTP_NO_CONTENT, $this->getClient()->getResponse()->getStatusCode(), $this->getClient()->getResponse()->getContent());
        static::assertNotEmpty($response->headers->get('Location'));
        static::assertEquals('http://localhost/api/v' . PlatformRequest::API_VERSION . '/named/' . $id, $response->headers->get('Location'));

        $this->assertEntityExists($this->getClient(), 'named', $id);
        $this->assertEntityExists($this->getClient(), 'named-optional-group', $groupId);

        $this->getClient()->request('DELETE', '/api/v' . PlatformRequest::API_VERSION . '/named/' . $id . '/optional-group/' . $groupId);
        static::assertSame(Response::HTTP_NO_CONTENT, $this->getClient()->getResponse()->getStatusCode(), $this->getClient()->getResponse()->getContent());

        $this->assertEntityExists($this->getClient(), 'named', $id);
        $this->assertEntityNotExists($this->getClient(), 'named-optional-group', $groupId);
    }

    public function testDeleteManyToMany(): void
    {
        $id = Uuid::randomHex();
        $category = Uuid::randomHex();

        $data = [
            'id' => $id,
            'productNumber' => Uuid::randomHex(),
            'stock' => 1,
            'name' => 'Test',
            'price' => ['gross' => 50, 'net' => 25, 'linked' => false],
            'tax' => ['name' => 'test', 'taxRate' => 10],
            'manufacturer' => ['name' => 'test'],
            'categories' => [
                ['id' => $category, 'name' => 'Test'],
            ],
        ];

        $this->getClient()->request('POST', '/api/v' . PlatformRequest::API_VERSION . '/product', $data);
        $response = $this->getClient()->getResponse();
        static::assertSame(Response::HTTP_NO_CONTENT, $this->getClient()->getResponse()->getStatusCode(), $this->getClient()->getResponse()->getContent());
        static::assertNotEmpty($response->headers->get('Location'));
        static::assertEquals('http://localhost/api/v' . PlatformRequest::API_VERSION . '/product/' . $id, $response->headers->get('Location'));

        $this->assertEntityExists($this->getClient(), 'product', $id);
        $this->assertEntityExists($this->getClient(), 'category', $category);

        $this->getClient()->request('DELETE', '/api/v' . PlatformRequest::API_VERSION . '/product/' . $id . '/categories/' . $category);
        static::assertSame(Response::HTTP_NO_CONTENT, $this->getClient()->getResponse()->getStatusCode(), $this->getClient()->getResponse()->getContent());

        $a = $this->getContainer()
            ->get(Connection::class)
            ->executeQuery(
                'SELECT * FROM product_category WHERE product_id = :pid AND category_id = :cid',
                ['pid' => Uuid::fromHexToBytes($id), 'cid' => Uuid::fromHexToBytes($category)]
            )->fetchAll();
        static::assertEmpty($a);

        $this->assertEntityExists($this->getClient(), 'product', $id);
        $this->assertEntityExists($this->getClient(), 'category', $category);
    }

    public function testResponseDataTypeOnWrite(): void
    {
        $id = Uuid::randomHex();

        $data = ['id' => $id, 'name' => $id, 'taxRate' => 50];

        // create without response
        $this->getClient()->request('POST', '/api/v' . PlatformRequest::API_VERSION . '/tax', $data);
        $response = $this->getClient()->getResponse();
        static::assertSame(Response::HTTP_NO_CONTENT, $this->getClient()->getResponse()->getStatusCode(), $this->getClient()->getResponse()->getContent());
        static::assertNotEmpty($response->headers->get('Location'));
        static::assertEquals('http://localhost/api/v' . PlatformRequest::API_VERSION . '/tax/' . $id, $response->headers->get('Location'));

        // update without response
        $this->getClient()->request('PATCH', '/api/v' . PlatformRequest::API_VERSION . '/tax/' . $id, ['name' => 'foo']);
        $response = $this->getClient()->getResponse();
        static::assertSame(Response::HTTP_NO_CONTENT, $this->getClient()->getResponse()->getStatusCode());
        static::assertNotEmpty($response->headers->get('Location'));
        static::assertEquals('http://localhost/api/v' . PlatformRequest::API_VERSION . '/tax/' . $id, $response->headers->get('Location'));

        // with response
        $this->getClient()->request('PATCH', '/api/v' . PlatformRequest::API_VERSION . '/tax/' . $id . '?_response=1', ['name' => 'foo']);
        $response = $this->getClient()->getResponse();
        static::assertSame(Response::HTTP_OK, $this->getClient()->getResponse()->getStatusCode());
        static::assertNull($response->headers->get('Location'));
    }

    public function testSearch(): void
    {
        $id = Uuid::randomHex();

        $data = [
            'id' => $id,
            'productNumber' => Uuid::randomHex(),
            'stock' => 1,
            'name' => 'Cotton Shirt',
            'tax' => ['name' => 'test', 'taxRate' => 10],
            'manufacturer' => ['name' => 'Shopware AG'],
            'price' => ['gross' => 50, 'net' => 25, 'linked' => false],
        ];

        $this->getClient()->request('POST', '/api/v' . PlatformRequest::API_VERSION . '/product', $data);
        $response = $this->getClient()->getResponse();
        static::assertSame(Response::HTTP_NO_CONTENT, $this->getClient()->getResponse()->getStatusCode(), $this->getClient()->getResponse()->getContent());
        static::assertNotEmpty($response->headers->get('Location'));
        static::assertEquals('http://localhost/api/v' . PlatformRequest::API_VERSION . '/product/' . $id, $response->headers->get('Location'));

        $data = [
            'page' => 1,
            'limit' => 5,
            'total-count-mode' => Criteria::TOTAL_COUNT_MODE_EXACT,
            'sort' => [
                [
                    'field' => 'product.stock',
                    'order' => 'desc',
                ],
                [
                    'field' => 'product.name',
                    'order' => 'desc',
                ],
            ],
            'filter' => [
                [
                    'type' => 'multi',
                    'queries' => [
                        [
                            'type' => 'range',
                            'field' => 'product.price',
                            'parameters' => [
                                'gt' => 49,
                                'lte' => 50,
                            ],
                        ],
                        [
                            'type' => 'equals',
                            'field' => 'product.manufacturer.name',
                            'value' => 'Shopware AG',
                        ],
                        [
                            'type' => 'equalsAny',
                            'field' => 'product.id',
                            'value' => $id,
                        ],
                    ],
                ],
            ],
            'query' => [
                [
                    'type' => 'score',
                    'query' => [
                        'type' => 'contains',
                        'field' => 'product.name',
                        'value' => 'Cotton',
                    ],
                ],
            ],
        ];

        $this->getClient()->request('POST', '/api/v' . PlatformRequest::API_VERSION . '/search/product', $data);
        $response = $this->getClient()->getResponse();
        $content = json_decode($response->getContent(), true);

        static::assertArrayHasKey('meta', $content, print_r($content, true));
        static::assertEquals(1, $content['meta']['total']);
        static::assertEquals($id, $content['data'][0]['id']);

        $this->getClient()->request('DELETE', '/api/v' . PlatformRequest::API_VERSION . '/product/' . $id);
        static::assertEquals(Response::HTTP_NO_CONTENT, $this->getClient()->getResponse()->getStatusCode());
    }

    public function testNestedSearchOnOneToMany(): void
    {
        $id = Uuid::randomHex();

        $ruleA = Uuid::randomHex();
        $ruleB = Uuid::randomHex();

        $this->getContainer()->get('rule.repository')->create([
            ['id' => $ruleA, 'name' => 'test', 'priority' => 1],
            ['id' => $ruleB, 'name' => 'test', 'priority' => 2],
        ], Context::createDefaultContext());

        $data = [
            'id' => $id,
            'productNumber' => Uuid::randomHex(),
            'stock' => 1,
            'name' => 'price test',
            'price' => ['gross' => 15, 'net' => 10, 'linked' => false],
            'manufacturer' => ['name' => 'test'],
            'tax' => ['name' => 'test', 'taxRate' => 15],
            'prices' => [
                [
                    'id' => $ruleA,
                    'currencyId' => Defaults::CURRENCY,
                    'quantityStart' => 1,
                    'ruleId' => $ruleA,
                    'price' => ['gross' => 15, 'net' => 10, 'linked' => false],
                ],
                [
                    'id' => $ruleB,
                    'currencyId' => Defaults::CURRENCY,
                    'quantityStart' => 1,
                    'ruleId' => $ruleB,
                    'price' => ['gross' => 10, 'net' => 8, 'linked' => false],
                ],
            ],
        ];

        $this->getContainer()->get('product.repository')
            ->create([$data], Context::createDefaultContext());

        $path = '/api/v' . PlatformRequest::API_VERSION . '/product/' . $id . '/prices';
        $this->getClient()->request('GET', $path);
        $responseData = json_decode($this->getClient()->getResponse()->getContent(), true);
        static::assertSame(Response::HTTP_OK, $this->getClient()->getResponse()->getStatusCode(), print_r($responseData, true));

        static::assertArrayHasKey('meta', $responseData);
        static::assertArrayHasKey('total', $responseData['meta']);
        static::assertSame(2, $responseData['meta']['total']);
        static::assertArrayHasKey('data', $responseData);

        $filter = [
            'filter' => [
                [
                    'type' => 'equals',
                    'field' => 'product_price.ruleId',
                    'value' => $ruleA,
                ],
            ],
        ];

        $path = '/api/v' . PlatformRequest::API_VERSION . '/search/product/' . $id . '/prices';
        $this->getClient()->request('POST', $path, $filter);
        $responseData = json_decode($this->getClient()->getResponse()->getContent(), true);

        static::assertSame(Response::HTTP_OK, $this->getClient()->getResponse()->getStatusCode(), print_r($responseData, true));
        static::assertArrayHasKey('meta', $responseData);
        static::assertArrayHasKey('total', $responseData['meta']);
        static::assertSame(1, $responseData['meta']['total']);
        static::assertArrayHasKey('data', $responseData);
    }

    public function testNestedSearchOnOneToManyWithAggregation(): void
    {
        $id = Uuid::randomHex();

        $ruleA = Uuid::randomHex();
        $ruleB = Uuid::randomHex();

        $this->getContainer()->get('rule.repository')->create([
            ['id' => $ruleA, 'name' => 'test', 'priority' => 1],
            ['id' => $ruleB, 'name' => 'test', 'priority' => 2],
        ], Context::createDefaultContext());

        $data = [
            'id' => $id,
            'productNumber' => Uuid::randomHex(),
            'stock' => 1,
            'name' => 'price test',
            'price' => ['gross' => 15, 'net' => 10, 'linked' => false],
            'manufacturer' => ['name' => 'test'],
            'tax' => ['name' => 'test', 'taxRate' => 15],
            'prices' => [
                [
                    'id' => $ruleA,
                    'currencyId' => Defaults::CURRENCY,
                    'quantityStart' => 1,
                    'ruleId' => $ruleA,
                    'price' => ['gross' => 15, 'net' => 10, 'linked' => false],
                ],
                [
                    'id' => $ruleB,
                    'currencyId' => Defaults::CURRENCY,
                    'quantityStart' => 1,
                    'ruleId' => $ruleB,
                    'price' => ['gross' => 10, 'net' => 8, 'linked' => false],
                ],
            ],
        ];

        $this->getContainer()->get('product.repository')
            ->create([$data], Context::createDefaultContext());

        $path = '/api/v' . PlatformRequest::API_VERSION . '/product/' . $id . '/prices';
        $this->getClient()->request('GET', $path);
        $responseData = json_decode($this->getClient()->getResponse()->getContent(), true);
        static::assertSame(Response::HTTP_OK, $this->getClient()->getResponse()->getStatusCode(), print_r($responseData, true));

        static::assertArrayHasKey('meta', $responseData);
        static::assertArrayHasKey('total', $responseData['meta']);
        static::assertSame(2, $responseData['meta']['total']);
        static::assertArrayHasKey('data', $responseData);

        $filter = [
            'aggregations' => [
                [
                    'name' => 'price_stats',
                    'type' => 'stats',
                    'field' => 'product_price.price',
                ],
            ],
        ];

        $path = '/api/v' . PlatformRequest::API_VERSION . '/search/product/' . $id . '/prices';
        $this->getClient()->request('POST', $path, $filter);
        $responseData = json_decode($this->getClient()->getResponse()->getContent(), true);

        static::assertSame(Response::HTTP_OK, $this->getClient()->getResponse()->getStatusCode(), print_r($responseData, true));
        static::assertArrayHasKey('aggregations', $responseData);
        static::assertArrayHasKey('price_stats', $responseData['aggregations']);
    }

    public function testSearchOnManyToMany(): void
    {
        $id = Uuid::randomHex();
        $categoryA = Uuid::randomHex();
        $categoryB = Uuid::randomHex();

        $data = [
            'id' => $id,
            'productNumber' => Uuid::randomHex(),
            'stock' => 1,
            'name' => 'price test',
            'price' => ['gross' => 15, 'net' => 10, 'linked' => false],
            'manufacturer' => ['name' => 'test'],
            'tax' => ['name' => 'test', 'taxRate' => 15],
            'categories' => [
                ['id' => $categoryA, 'name' => 'A'],
                ['id' => $categoryB, 'name' => 'B'],
            ],
        ];

        $this->getContainer()->get('product.repository')
            ->create([$data], Context::createDefaultContext());

        $path = '/api/v' . PlatformRequest::API_VERSION . '/product/' . $id . '/categories';
        $this->getClient()->request('GET', $path);
        $responseData = json_decode($this->getClient()->getResponse()->getContent(), true);
        static::assertSame(Response::HTTP_OK, $this->getClient()->getResponse()->getStatusCode(), print_r($responseData, true));

        static::assertArrayHasKey('meta', $responseData);
        static::assertArrayHasKey('total', $responseData['meta']);
        static::assertSame(2, $responseData['meta']['total']);
        static::assertArrayHasKey('data', $responseData);

        $filter = [
            'filter' => [
                [
                    'type' => 'equals',
                    'field' => 'category.name',
                    'value' => 'A',
                ],
            ],
        ];

        $path = '/api/v' . PlatformRequest::API_VERSION . '/search/product/' . $id . '/categories';
        $this->getClient()->request('POST', $path, $filter);
        $responseData = json_decode($this->getClient()->getResponse()->getContent(), true);

        static::assertSame(Response::HTTP_OK, $this->getClient()->getResponse()->getStatusCode(), print_r($responseData, true));
        static::assertArrayHasKey('meta', $responseData);
        static::assertArrayHasKey('total', $responseData['meta']);
        static::assertSame(1, $responseData['meta']['total']);
        static::assertArrayHasKey('data', $responseData);
    }

    public function testSimpleFilter(): void
    {
        $id = Uuid::randomHex();

        $data = [
            'id' => $id,
            'productNumber' => Uuid::randomHex(),
            'stock' => 1,
            'name' => 'Wool Shirt',
            'tax' => ['name' => 'test', 'taxRate' => 10],
            'manufacturer' => ['name' => 'Shopware AG'],
            'price' => ['gross' => 8300, 'net' => 8300, 'linked' => false],
        ];

        $this->getClient()->request('POST', '/api/v' . PlatformRequest::API_VERSION . '/product', $data);
        $response = $this->getClient()->getResponse();
        static::assertSame(Response::HTTP_NO_CONTENT, $this->getClient()->getResponse()->getStatusCode(), $this->getClient()->getResponse()->getContent());
        static::assertNotEmpty($response->headers->get('Location'));
        static::assertEquals('http://localhost/api/v' . PlatformRequest::API_VERSION . '/product/' . $id, $response->headers->get('Location'));

        $data = [
            'filter' => [
                'product.id' => $id,
                'product.price' => 8300,
                'product.name' => 'Wool Shirt',
            ],
        ];

        $this->getClient()->request('GET', '/api/v' . PlatformRequest::API_VERSION . '/product', $data);
        $response = $this->getClient()->getResponse();
        $content = json_decode($response->getContent(), true);
        static::assertEquals(1, $content['meta']['total']);
        static::assertEquals($id, $content['data'][0]['id']);
    }

    public function testAggregation(): void
    {
        $manufacturerName = Uuid::randomHex();

        $productA = Uuid::randomHex();
        $data = [
            'id' => $productA,
            'productNumber' => Uuid::randomHex(),
            'name' => 'Wool Shirt',
            'tax' => ['name' => 'test', 'taxRate' => 10],
            'manufacturer' => ['name' => $manufacturerName],
            'price' => ['gross' => 8300, 'net' => 8300, 'linked' => false],
            'stock' => 50,
        ];
        $this->getClient()->request('POST', '/api/v' . PlatformRequest::API_VERSION . '/product', $data);
        static::assertEquals(Response::HTTP_NO_CONTENT, $this->getClient()->getResponse()->getStatusCode());

        $productB = Uuid::randomHex();
        $data = [
            'id' => $productB,
            'productNumber' => Uuid::randomHex(),
            'name' => 'Wool Shirt 2',
            'tax' => ['name' => 'test', 'taxRate' => 10],
            'manufacturer' => ['name' => $manufacturerName],
            'price' => ['gross' => 8300, 'net' => 8300, 'linked' => false],
            'stock' => 100,
        ];
        $this->getClient()->request('POST', '/api/v' . PlatformRequest::API_VERSION . '/product', $data);
        static::assertEquals(Response::HTTP_NO_CONTENT, $this->getClient()->getResponse()->getStatusCode());

        $data = [
            'aggregations' => [
                ['name' => 'product_count', 'type' => 'count', 'field' => 'product.id'],
                ['name' => 'product_stats', 'type' => 'stats', 'field' => 'product.stock'],
            ],
            'filter' => [
                [
                    'type' => 'multi',
                    'queries' => [
                        [
                            'type' => 'equals',
                            'field' => 'product.manufacturer.name',
                            'value' => $manufacturerName,
                        ],
                    ],
                ],
            ],
        ];

        $this->getClient()->setServerParameter('HTTP_ACCEPT', 'application/json');
        $this->getClient()->request('POST', '/api/v' . PlatformRequest::API_VERSION . '/search/product', $data);
        $response = $this->getClient()->getResponse();

        $content = json_decode($response->getContent(), true);

        static::assertEquals(Response::HTTP_OK, $response->getStatusCode(), print_r($response->getContent(), true));
        static::assertNotEmpty($content);

        static::assertArrayHasKey('aggregations', $content);
        $aggregations = $content['aggregations'];

        static::assertArrayHasKey('product_count', $aggregations, print_r($aggregations, true));
        $productCount = $aggregations['product_count'];
        static::assertEquals(2, $productCount[0]['count']);

        static::assertArrayHasKey('product_stats', $aggregations);
        $productStats = $aggregations['product_stats'][0];
        static::assertEquals(2, $productStats['count']);
        static::assertEquals(75, $productStats['avg']);
        static::assertEquals(150, $productStats['sum']);
        static::assertEquals(50, $productStats['min']);
        static::assertEquals(100, $productStats['max']);
    }

    public function testParentChildLocation(): void
    {
        $childId = Uuid::randomHex();
        $parentId = Uuid::randomHex();

        $data = [
            'id' => $childId,
            'name' => 'Child Language',
            'localeId' => $this->getLocaleIdOfSystemLanguage(),
            'parent' => [
                'id' => $parentId,
                'name' => 'Parent Language',
                'locale' => [
                    'code' => 'x-tst_' . Uuid::randomHex(),
                    'name' => 'test name',
                    'territory' => 'test territory',
                ],
                'translationCode' => [
                    'code' => 'x-tst_' . Uuid::randomHex(),
                    'name' => 'test name',
                    'territory' => 'test territory',
                ],
            ],
        ];

        $this->getClient()->request('POST', '/api/v' . PlatformRequest::API_VERSION . '/language', $data);
        $response = $this->getClient()->getResponse();
        static::assertSame(Response::HTTP_NO_CONTENT, $this->getClient()->getResponse()->getStatusCode(), $this->getClient()->getResponse()->getContent());
        static::assertNotEmpty($response->headers->get('Location'));
        static::assertEquals('http://localhost/api/v' . PlatformRequest::API_VERSION . '/language/' . $childId, $response->headers->get('Location'));
    }

    public function testJsonApiResponseSingle(): void
    {
        $id = Uuid::randomHex();
        $insertData = ['id' => $id, 'name' => 'test'];

        $this->getClient()->request('POST', '/api/v' . PlatformRequest::API_VERSION . '/category', [], [], [], json_encode($insertData));
        $response = $this->getClient()->getResponse();

        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode(), $response->getContent());
        static::assertNotEmpty($response->headers->get('Location'));

        $this->getClient()->request('GET', $response->headers->get('Location'));
        $response = $this->getClient()->getResponse();
        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), $response->getContent());

        $respData = json_decode($response->getContent(), true);

        static::assertArrayHasKey('data', $respData);
        static::assertArrayHasKey('links', $respData);
        static::assertArrayHasKey('included', $respData);

        $catData = $respData['data'];
        static::assertArrayHasKey('type', $catData);
        static::assertArrayHasKey('id', $catData);
        static::assertArrayHasKey('attributes', $catData);
        static::assertArrayHasKey('links', $catData);
        static::assertArrayHasKey('relationships', $catData);
        static::assertArrayHasKey('translations', $catData['relationships']);
        static::assertArrayHasKey('meta', $catData);
        static::assertArrayHasKey('translated', $catData['attributes']);
        static::assertArrayHasKey('name', $catData['attributes']['translated']);

        static::assertEquals($id, $catData['id']);
        static::assertEquals('category', $catData['type']);
        static::assertEquals($insertData['name'], $catData['attributes']['name']);
        static::assertEquals($insertData['name'], $catData['attributes']['translated']['name']);
    }

    public function testJsonApiResponseMulti(): void
    {
        $insertData = [
            ['name' => 'test'],
            ['name' => 'test_2'],
        ];

        $this->getClient()->request('POST', '/api/v' . PlatformRequest::API_VERSION . '/category', [], [], [], json_encode($insertData[0]));
        $response = $this->getClient()->getResponse();
        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode(), $response->getContent());

        $this->getClient()->request('POST', '/api/v' . PlatformRequest::API_VERSION . '/category', [], [], [], json_encode($insertData[1]));
        $response = $this->getClient()->getResponse();
        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode(), $response->getContent());

        $this->getClient()->request('GET', '/api/v' . PlatformRequest::API_VERSION . '/category?sort=name');
        $response = $this->getClient()->getResponse();
        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), $response->getContent());

        $respData = json_decode($response->getContent(), true);
        static::assertArrayHasKey('data', $respData);
        static::assertArrayHasKey('links', $respData);
        static::assertArrayHasKey('included', $respData);
        static::assertCount(2, $respData['data']);

        $data = $respData['data'];
        static::assertEquals('category', $data[0]['type']);
        static::assertEquals($insertData[0]['name'], $data[0]['attributes']['name']);
        static::assertEquals($insertData[0]['name'], $data[0]['attributes']['translated']['name']);

        static::assertEquals('category', $data[1]['type']);
        static::assertEquals($insertData[1]['name'], $data[1]['attributes']['name']);
        static::assertEquals($insertData[1]['name'], $data[1]['attributes']['translated']['name']);
    }

    public function testCreateNewVersion(): void
    {
        $id = Uuid::randomHex();

        $data = ['id' => $id, 'name' => 'test category'];

        $this->getClient()->request('POST', '/api/v' . PlatformRequest::API_VERSION . '/category', $data);
        $response = $this->getClient()->getResponse();

        /* @var Response $response */
        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode(), $response->getContent());

        static::assertNotEmpty($response->headers->get('Location'));

        $this->getClient()->request(
            'POST',
            sprintf('/api/v%s/_action/version/category/%s', PlatformRequest::API_VERSION, $id)
        );
        $response = $this->getClient()->getResponse();
        $content = json_decode($response->getContent(), true);

        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), $response->getContent());
        static::assertTrue(Uuid::isValid($content['versionId']));
        static::assertNull($content['versionName']);
        static::assertEquals($id, $content['id']);
        static::assertEquals('category', $content['entity']);
    }

    public function testCloneEntity(): void
    {
        $id = Uuid::randomHex();
        $data = [
            'id' => $id,
            'name' => 'test tax clone',
            'taxRate' => 15,
        ];

        $this->getClient()->request('POST', '/api/v' . PlatformRequest::API_VERSION . '/tax', [], [], [], json_encode($data));
        $response = $this->getClient()->getResponse();
        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode(), $response->getContent());

        $this->getClient()->request('GET', '/api/v' . PlatformRequest::API_VERSION . '/tax/' . $id);
        $response = $this->getClient()->getResponse();
        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), $response->getContent());

        $tax = json_decode($response->getContent(), true);
        static::assertArrayHasKey('data', $tax);
        static::assertEquals($id, $tax['data']['id']);

        $this->getClient()->request('POST', '/api/v' . PlatformRequest::API_VERSION . '/_action/clone/tax/' . $id, [], [], [], json_encode($data));
        $response = $this->getClient()->getResponse();
        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), $response->getContent());

        $data = json_decode($response->getContent(), true);
        static::assertArrayHasKey('id', $data);
        static::assertNotEquals($id, $data['id']);

        $newId = $data['id'];
        $this->getClient()->request('GET', '/api/v' . PlatformRequest::API_VERSION . '/tax/' . $newId);
        $response = $this->getClient()->getResponse();
        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), $response->getContent());

        $data = json_decode($response->getContent(), true);
        static::assertEquals(15, $data['data']['attributes']['taxRate']);
    }
}
