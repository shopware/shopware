<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Api\Controller;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Rule\Container\AndRule;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Core\Framework\Test\TestCaseBase\AdminApiTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\FilesystemBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\Response;

class ApiControllerTest extends TestCase
{
    use KernelTestBehaviour,
        FilesystemBehaviour,
        AdminApiTestBehaviour;

    /**
     * @var Connection
     */
    private $connection;

    public function setUp(): void
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
        $id = Uuid::uuid4()->getHex();

        $data = [
            'id' => $id,
            'name' => $id,
            'tax' => ['name' => 'test', 'taxRate' => 10],
            'manufacturer' => ['name' => 'test'],
            'price' => ['gross' => 50, 'net' => 25],
        ];

        $this->getClient()->request('POST', '/api/v' . PlatformRequest::API_VERSION . '/product', [], [], [], json_encode($data));
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
        $id = Uuid::uuid4()->getHex();

        $data = ['id' => $id, 'name' => $id];

        $this->getClient()->request('POST', '/api/v' . PlatformRequest::API_VERSION . '/country', [], [], [], json_encode($data));
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

        $this->getClient()->request('POST', '/api/v' . PlatformRequest::API_VERSION . '/country/' . $id . '/states/', [], [], [], json_encode($data));
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
        $id = Uuid::uuid4()->getHex();
        $manufacturer = Uuid::uuid4()->getHex();

        $data = [
            'id' => $id,
            'name' => $id,
            'tax' => ['name' => 'test', 'taxRate' => 10],
            'manufacturer' => ['name' => 'test'],
            'price' => ['gross' => 50, 'net' => 25],
        ];

        $this->getClient()->request('POST', '/api/v' . PlatformRequest::API_VERSION . '/product', [], [], [], json_encode($data));
        $response = $this->getClient()->getResponse();
        static::assertSame(Response::HTTP_NO_CONTENT, $this->getClient()->getResponse()->getStatusCode(), 'Create product failed id:' . $id);
        static::assertNotEmpty($response->headers->get('Location'));
        static::assertEquals('http://localhost/api/v' . PlatformRequest::API_VERSION . '/product/' . $id, $response->headers->get('Location'));

        $data = [
            'id' => $manufacturer,
            'name' => 'Manufacturer - 1',
            'link' => 'https://www.shopware.com',
        ];

        $this->getClient()->request('POST', '/api/v' . PlatformRequest::API_VERSION . '/product/' . $id . '/manufacturer', [], [], [], json_encode($data));
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
        $id = Uuid::uuid4()->getHex();

        $data = [
            'id' => $id,
            'name' => $id,
            'tax' => ['name' => 'test', 'taxRate' => 10],
            'manufacturer' => ['name' => 'test'],
            'price' => ['gross' => 50, 'net' => 25],
        ];

        $this->getClient()->request('POST', '/api/v' . PlatformRequest::API_VERSION . '/product', [], [], [], json_encode($data));
        $response = $this->getClient()->getResponse();
        static::assertSame(Response::HTTP_NO_CONTENT, $this->getClient()->getResponse()->getStatusCode(), $this->getClient()->getResponse()->getContent());
        static::assertNotEmpty($response->headers->get('Location'));
        static::assertEquals('http://localhost/api/v' . PlatformRequest::API_VERSION . '/product/' . $id, $response->headers->get('Location'));

        $data = [
            'id' => $id,
            'name' => 'Category - 1',
        ];

        $this->getClient()->request('POST', '/api/v' . PlatformRequest::API_VERSION . '/product/' . $id . '/categories/', [], [], [], json_encode($data));
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
        $id = Uuid::uuid4();

        $data = [
            'id' => $id->getHex(),
            'name' => $id->getHex(),
            'tax' => ['name' => 'test', 'taxRate' => 10],
            'manufacturer' => ['name' => 'test'],
            'price' => ['gross' => 50, 'net' => 25],
        ];

        $this->getClient()->request('POST', '/api/v' . PlatformRequest::API_VERSION . '/product', [], [], [], json_encode($data));
        $response = $this->getClient()->getResponse();
        static::assertSame(Response::HTTP_NO_CONTENT, $this->getClient()->getResponse()->getStatusCode(), $this->getClient()->getResponse()->getContent());
        static::assertNotEmpty($response->headers->get('Location'));
        static::assertEquals('http://localhost/api/v' . PlatformRequest::API_VERSION . '/product/' . $id->getHex(), $response->headers->get('Location'));

        $this->assertEntityExists($this->getClient(), 'product', $id->getHex());

        $this->getClient()->request('DELETE', '/api/v' . PlatformRequest::API_VERSION . '/product/' . $id->getHex());
        static::assertSame(Response::HTTP_NO_CONTENT, $this->getClient()->getResponse()->getStatusCode(), $this->getClient()->getResponse()->getContent());

        $this->assertEntityNotExists($this->getClient(), 'product', $id->getHex());
    }

    public function testDeleteOneToMany(): void
    {
        $id = Uuid::uuid4();
        $stateId = Uuid::uuid4();

        $data = [
            'id' => $id->getHex(),
            'name' => $id->getHex(),
            'states' => [
                ['id' => $stateId->getHex(), 'shortCode' => 'test', 'name' => 'test'],
            ],
        ];

        $this->getClient()->request('POST', '/api/v' . PlatformRequest::API_VERSION . '/country', [], [], [], json_encode($data));
        $response = $this->getClient()->getResponse();
        static::assertSame(Response::HTTP_NO_CONTENT, $this->getClient()->getResponse()->getStatusCode(), $this->getClient()->getResponse()->getContent());
        static::assertNotEmpty($response->headers->get('Location'));
        static::assertEquals('http://localhost/api/v' . PlatformRequest::API_VERSION . '/country/' . $id->getHex(), $response->headers->get('Location'));

        $this->assertEntityExists($this->getClient(), 'country', $id->getHex());
        $this->assertEntityExists($this->getClient(), 'country-state', $stateId->getHex());

        $this->getClient()->request('DELETE', '/api/v' . PlatformRequest::API_VERSION . '/country/' . $id->getHex() . '/states/' . $stateId->getHex(), $data);
        static::assertSame(Response::HTTP_NO_CONTENT, $this->getClient()->getResponse()->getStatusCode(), $this->getClient()->getResponse()->getContent());

        $this->assertEntityExists($this->getClient(), 'country', $id->getHex());
        $this->assertEntityNotExists($this->getClient(), 'country-state', $stateId->getHex());
    }

    public function testDeleteManyToOne(): void
    {
        $id = Uuid::uuid4()->getHex();
        $groupId = Uuid::uuid4()->getHex();

        $data = [
            'id' => $id,
            'name' => 'Test product',
            'optionalGroup' => [
                'id' => $groupId,
                'name' => 'Gramm',
            ],
        ];
        $this->getClient()->request('POST', '/api/v' . PlatformRequest::API_VERSION . '/named', [], [], [], json_encode($data));
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
        $id = Uuid::uuid4();
        $category = Uuid::uuid4();

        $data = [
            'id' => $id->getHex(),
            'name' => 'Test',
            'price' => ['gross' => 50, 'net' => 25],
            'tax' => ['name' => 'test', 'taxRate' => 10],
            'manufacturer' => ['name' => 'test'],
            'categories' => [
                ['id' => $category->getHex(), 'name' => 'Test'],
            ],
        ];

        $this->getClient()->request('POST', '/api/v' . PlatformRequest::API_VERSION . '/product', [], [], [], json_encode($data));
        $response = $this->getClient()->getResponse();
        static::assertSame(Response::HTTP_NO_CONTENT, $this->getClient()->getResponse()->getStatusCode(), $this->getClient()->getResponse()->getContent());
        static::assertNotEmpty($response->headers->get('Location'));
        static::assertEquals('http://localhost/api/v' . PlatformRequest::API_VERSION . '/product/' . $id->getHex(), $response->headers->get('Location'));

        $this->assertEntityExists($this->getClient(), 'product', $id->getHex());
        $this->assertEntityExists($this->getClient(), 'category', $category->getHex());

        $this->getClient()->request('DELETE', '/api/v' . PlatformRequest::API_VERSION . '/product/' . $id->getHex() . '/categories/' . $category->getHex());
        static::assertSame(Response::HTTP_NO_CONTENT, $this->getClient()->getResponse()->getStatusCode(), $this->getClient()->getResponse()->getContent());

        $a = $this->getContainer()->get(Connection::class)->executeQuery('SELECT * FROM product_category WHERE product_id = :pid AND category_id = :cid', ['pid' => $id->getBytes(), 'cid' => $category->getBytes()])->fetchAll();
        static::assertEmpty($a);

        $this->assertEntityExists($this->getClient(), 'product', $id->getHex());
        $this->assertEntityExists($this->getClient(), 'category', $category->getHex());
    }

    public function testResponseDataTypeOnWrite(): void
    {
        $id = Uuid::uuid4();

        $data = ['id' => $id->getHex(), 'name' => $id->getHex(), 'taxRate' => 50];

        // create without response
        $this->getClient()->request('POST', '/api/v' . PlatformRequest::API_VERSION . '/tax', [], [], [], json_encode($data));
        $response = $this->getClient()->getResponse();
        static::assertSame(Response::HTTP_NO_CONTENT, $this->getClient()->getResponse()->getStatusCode(), $this->getClient()->getResponse()->getContent());
        static::assertNotEmpty($response->headers->get('Location'));
        static::assertEquals('http://localhost/api/v' . PlatformRequest::API_VERSION . '/tax/' . $id->getHex(), $response->headers->get('Location'));

        // update without response
        $this->getClient()->request('PATCH', '/api/v' . PlatformRequest::API_VERSION . '/tax/' . $id->getHex(), [], [], [], json_encode(['name' => 'foo']));
        $response = $this->getClient()->getResponse();
        static::assertSame(Response::HTTP_NO_CONTENT, $this->getClient()->getResponse()->getStatusCode());
        static::assertNotEmpty($response->headers->get('Location'));
        static::assertEquals('http://localhost/api/v' . PlatformRequest::API_VERSION . '/tax/' . $id->getHex(), $response->headers->get('Location'));

        // with response
        $this->getClient()->request('PATCH', '/api/v' . PlatformRequest::API_VERSION . '/tax/' . $id->getHex() . '?_response=1', [], [], [], json_encode(['name' => 'foo']));
        $response = $this->getClient()->getResponse();
        static::assertSame(Response::HTTP_OK, $this->getClient()->getResponse()->getStatusCode());
        static::assertNull($response->headers->get('Location'));
    }

    public function testSearch(): void
    {
        $id = Uuid::uuid4()->getHex();

        $data = [
            'id' => $id,
            'name' => 'Cotton Shirt',
            'tax' => ['name' => 'test', 'taxRate' => 10],
            'manufacturer' => ['name' => 'Shopware AG'],
            'price' => ['gross' => 50, 'net' => 25],
        ];

        $this->getClient()->request('POST', '/api/v' . PlatformRequest::API_VERSION . '/product', [], [], [], json_encode($data));
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
                    'type' => 'nested',
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
                            'type' => 'term',
                            'field' => 'product.manufacturer.name',
                            'value' => 'Shopware AG',
                        ],
                        [
                            'type' => 'terms',
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
                        'type' => 'match',
                        'field' => 'product.name',
                        'value' => 'Cotton',
                    ],
                ],
            ],
        ];

        $this->getClient()->request('POST', '/api/v' . PlatformRequest::API_VERSION . '/search/product', [], [], [], json_encode($data));
        $response = $this->getClient()->getResponse();
        $content = json_decode($response->getContent(), true);

        static::assertEquals(1, $content['meta']['total']);
        static::assertEquals($id, $content['data'][0]['id']);

        $this->getClient()->request('DELETE', '/api/v' . PlatformRequest::API_VERSION . '/product/' . $id);
        static::assertEquals(Response::HTTP_NO_CONTENT, $this->getClient()->getResponse()->getStatusCode());
    }

    public function testNestedSearchOnOneToMany(): void
    {
        $id = Uuid::uuid4()->getHex();

        $ruleA = Uuid::uuid4()->getHex();
        $ruleB = Uuid::uuid4()->getHex();

        $this->getContainer()->get('rule.repository')->create([
            ['id' => $ruleA, 'name' => 'test', 'payload' => new AndRule(), 'priority' => 1],
            ['id' => $ruleB, 'name' => 'test', 'payload' => new AndRule(), 'priority' => 2],
        ], Context::createDefaultContext());

        $data = [
            'id' => $id,
            'name' => 'price test',
            'price' => ['gross' => 15, 'net' => 10],
            'manufacturer' => ['name' => 'test'],
            'tax' => ['name' => 'test', 'taxRate' => 15],
            'priceRules' => [
                [
                    'id' => $ruleA,
                    'currencyId' => Defaults::CURRENCY,
                    'quantityStart' => 1,
                    'ruleId' => $ruleA,
                    'price' => ['gross' => 15, 'net' => 10],
                ],
                [
                    'id' => $ruleB,
                    'currencyId' => Defaults::CURRENCY,
                    'quantityStart' => 1,
                    'ruleId' => $ruleB,
                    'price' => ['gross' => 10, 'net' => 8],
                ],
            ],
        ];

        $this->getContainer()->get('product.repository')
            ->create([$data], Context::createDefaultContext());

        $path = '/api/v' . PlatformRequest::API_VERSION . '/product/' . $id . '/priceRules';
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
                    'type' => 'term',
                    'field' => 'product_price_rule.ruleId',
                    'value' => $ruleA,
                ],
            ],
        ];

        $path = '/api/v' . PlatformRequest::API_VERSION . '/search/product/' . $id . '/priceRules';
        $this->getClient()->request('POST', $path, [], [], [], json_encode($filter));
        $responseData = json_decode($this->getClient()->getResponse()->getContent(), true);

        static::assertSame(Response::HTTP_OK, $this->getClient()->getResponse()->getStatusCode(), print_r($responseData, true));
        static::assertArrayHasKey('meta', $responseData);
        static::assertArrayHasKey('total', $responseData['meta']);
        static::assertSame(1, $responseData['meta']['total']);
        static::assertArrayHasKey('data', $responseData);
    }

    public function testNestedSearchOnOneToManyWithAggregation(): void
    {
        $id = Uuid::uuid4()->getHex();

        $ruleA = Uuid::uuid4()->getHex();
        $ruleB = Uuid::uuid4()->getHex();

        $this->getContainer()->get('rule.repository')->create([
            ['id' => $ruleA, 'name' => 'test', 'payload' => new AndRule(), 'priority' => 1],
            ['id' => $ruleB, 'name' => 'test', 'payload' => new AndRule(), 'priority' => 2],
        ], Context::createDefaultContext());

        $data = [
            'id' => $id,
            'name' => 'price test',
            'price' => ['gross' => 15, 'net' => 10],
            'manufacturer' => ['name' => 'test'],
            'tax' => ['name' => 'test', 'taxRate' => 15],
            'priceRules' => [
                [
                    'id' => $ruleA,
                    'currencyId' => Defaults::CURRENCY,
                    'quantityStart' => 1,
                    'ruleId' => $ruleA,
                    'price' => ['gross' => 15, 'net' => 10],
                ],
                [
                    'id' => $ruleB,
                    'currencyId' => Defaults::CURRENCY,
                    'quantityStart' => 1,
                    'ruleId' => $ruleB,
                    'price' => ['gross' => 10, 'net' => 8],
                ],
            ],
        ];

        $this->getContainer()->get('product.repository')
            ->create([$data], Context::createDefaultContext());

        $path = '/api/v' . PlatformRequest::API_VERSION . '/product/' . $id . '/priceRules';
        $this->getClient()->request('GET', $path);
        $responseData = json_decode($this->getClient()->getResponse()->getContent(), true);
        static::assertSame(Response::HTTP_OK, $this->getClient()->getResponse()->getStatusCode(), print_r($responseData, true));

        static::assertArrayHasKey('meta', $responseData);
        static::assertArrayHasKey('total', $responseData['meta']);
        static::assertSame(2, $responseData['meta']['total']);
        static::assertArrayHasKey('data', $responseData);

        $filter = [
            'aggregations' => [
                'price_stats' => [
                    'stats' => ['field' => 'product_price_rule.price'],
                ],
            ],
        ];

        $path = '/api/v' . PlatformRequest::API_VERSION . '/search/product/' . $id . '/priceRules';
        $this->getClient()->request('POST', $path, [], [], [], json_encode($filter));
        $responseData = json_decode($this->getClient()->getResponse()->getContent(), true);

        static::assertSame(Response::HTTP_OK, $this->getClient()->getResponse()->getStatusCode(), print_r($responseData, true));
        static::assertArrayHasKey('aggregations', $responseData);
        static::assertArrayHasKey('price_stats', $responseData['aggregations']);
    }

    public function testSearchOnManyToMany(): void
    {
        $id = Uuid::uuid4()->getHex();
        $categoryA = Uuid::uuid4()->getHex();
        $categoryB = Uuid::uuid4()->getHex();

        $data = [
            'id' => $id,
            'name' => 'price test',
            'price' => ['gross' => 15, 'net' => 10],
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
                    'type' => 'term',
                    'field' => 'category.name',
                    'value' => 'A',
                ],
            ],
        ];

        $path = '/api/v' . PlatformRequest::API_VERSION . '/search/product/' . $id . '/categories';
        $this->getClient()->request('POST', $path, [], [], [], json_encode($filter));
        $responseData = json_decode($this->getClient()->getResponse()->getContent(), true);

        static::assertSame(Response::HTTP_OK, $this->getClient()->getResponse()->getStatusCode(), print_r($responseData, true));
        static::assertArrayHasKey('meta', $responseData);
        static::assertArrayHasKey('total', $responseData['meta']);
        static::assertSame(1, $responseData['meta']['total']);
        static::assertArrayHasKey('data', $responseData);
    }

    public function testSimpleFilter(): void
    {
        $id = Uuid::uuid4()->getHex();

        $data = [
            'id' => $id,
            'name' => 'Wool Shirt',
            'tax' => ['name' => 'test', 'taxRate' => 10],
            'manufacturer' => ['name' => 'Shopware AG'],
            'price' => ['gross' => 8300, 'net' => 8300],
        ];

        $this->getClient()->request('POST', '/api/v' . PlatformRequest::API_VERSION . '/product', [], [], [], json_encode($data));
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
        $manufacturerName = Uuid::uuid4()->getHex();

        $productA = Uuid::uuid4()->getHex();
        $data = [
            'id' => $productA,
            'name' => 'Wool Shirt',
            'tax' => ['name' => 'test', 'taxRate' => 10],
            'manufacturer' => ['name' => $manufacturerName],
            'price' => ['gross' => 8300, 'net' => 8300],
            'stock' => 50,
        ];
        $this->getClient()->request('POST', '/api/v' . PlatformRequest::API_VERSION . '/product', [], [], [], json_encode($data));
        static::assertEquals(Response::HTTP_NO_CONTENT, $this->getClient()->getResponse()->getStatusCode());

        $productB = Uuid::uuid4()->getHex();
        $data = [
            'id' => $productB,
            'name' => 'Wool Shirt 2',
            'tax' => ['name' => 'test', 'taxRate' => 10],
            'manufacturer' => ['name' => $manufacturerName],
            'price' => ['gross' => 8300, 'net' => 8300],
            'stock' => 100,
        ];
        $this->getClient()->request('POST', '/api/v' . PlatformRequest::API_VERSION . '/product', [], [], [], json_encode($data));
        static::assertEquals(Response::HTTP_NO_CONTENT, $this->getClient()->getResponse()->getStatusCode());

        $data = [
            'aggregations' => [
                'product_count' => ['count' => ['field' => 'product.id']],
                'product_stats' => ['stats' => ['field' => 'product.stock']],
            ],
            'filter' => [
                [
                    'type' => 'nested',
                    'queries' => [
                        [
                            'type' => 'term',
                            'field' => 'product.manufacturer.name',
                            'value' => $manufacturerName,
                        ],
                    ],
                ],
            ],
        ];

        $this->getClient()->setServerParameter('HTTP_ACCEPT', 'application/json');
        $this->getClient()->request('POST', '/api/v' . PlatformRequest::API_VERSION . '/search/product', [], [], [], json_encode($data));
        $response = $this->getClient()->getResponse();

        $content = json_decode($response->getContent(), true);

        static::assertEquals(Response::HTTP_OK, $response->getStatusCode(), print_r($response->getContent(), true));
        static::assertNotEmpty($content);

        static::assertArrayHasKey('aggregations', $content);
        $aggregations = $content['aggregations'];

        static::assertArrayHasKey('product_count', $aggregations, print_r($aggregations, true));
        $productCount = $aggregations['product_count'];
        static::assertEquals(2, $productCount['count']);

        static::assertArrayHasKey('product_stats', $aggregations);
        $productStats = $aggregations['product_stats'];
        static::assertEquals(2, $productStats['count']);
        static::assertEquals(75, $productStats['avg']);
        static::assertEquals(150, $productStats['sum']);
        static::assertEquals(50, $productStats['min']);
        static::assertEquals(100, $productStats['max']);
    }

    public function testParentChildLocation(): void
    {
        $childId = Uuid::uuid4()->getHex();
        $parentId = Uuid::uuid4()->getHex();

        $data = [
            'id' => $childId,
            'name' => 'Child Language',
            'localeId' => Defaults::LOCALE_SYSTEM,
            'parent' => [
                'id' => $parentId,
                'name' => 'Parent Language',
                'locale' => [
                    'code' => 'x-tst_' . Uuid::uuid4()->getHex(),
                    'name' => 'test name',
                    'territory' => 'test territory',
                ],
                'translationCode' => [
                    'code' => 'x-tst_' . Uuid::uuid4()->getHex(),
                    'name' => 'test name',
                    'territory' => 'test territory',
                ],
            ],
        ];

        $this->getClient()->request('POST', '/api/v' . PlatformRequest::API_VERSION . '/language', [], [], [], json_encode($data));
        $response = $this->getClient()->getResponse();
        static::assertSame(Response::HTTP_NO_CONTENT, $this->getClient()->getResponse()->getStatusCode(), $this->getClient()->getResponse()->getContent());
        static::assertNotEmpty($response->headers->get('Location'));
        static::assertEquals('http://localhost/api/v' . PlatformRequest::API_VERSION . '/language/' . $childId, $response->headers->get('Location'));
    }

    public function testJsonApiResponseSingle(): void
    {
        $id = Uuid::uuid4()->getHex();
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
        static::assertArrayHasKey('viewData', $catData['meta']);
        static::assertArrayHasKey('name', $catData['meta']['viewData']);

        static::assertEquals($id, $catData['id']);
        static::assertEquals('category', $catData['type']);
        static::assertEquals($insertData['name'], $catData['attributes']['name']);
        static::assertEquals($insertData['name'], $catData['meta']['viewData']['name']);

        $translations = $catData['relationships']['translations'];
        static::assertCount(1, $translations['data']);
        static::assertEquals($id . '-' . Defaults::LANGUAGE_EN, $translations['data'][0]['id']);
        static::assertEquals('category_translation', $translations['data'][0]['type']);
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
        static::assertEquals($insertData[0]['name'], $data[0]['meta']['viewData']['name']);

        static::assertEquals('category', $data[1]['type']);
        static::assertEquals($insertData[1]['name'], $data[1]['attributes']['name']);
        static::assertEquals($insertData[1]['name'], $data[1]['meta']['viewData']['name']);
    }
}
