<?php declare(strict_types=1);

namespace Shopware\Rest\Test\Controller;

use Doctrine\DBAL\Connection;
use Shopware\Api\Entity\Search\Criteria;
use Shopware\Framework\Struct\Uuid;
use Shopware\PlatformRequest;
use Shopware\Rest\Test\ApiTestCase;
use Symfony\Component\HttpFoundation\Response;

class ApiControllerTest extends ApiTestCase
{
    public function testInsert(): void
    {
        $id = Uuid::uuid4()->getHex();

        $data = [
            'id' => $id,
            'name' => $id,
            'tax' => ['name' => 'test', 'rate' => 10],
            'manufacturer' => ['name' => 'test'],
            'price' => ['gross' => 50, 'net' => 25],
        ];

        $this->apiClient->request('POST', '/api/v' . PlatformRequest::API_VERSION . '/product', [], [], [], json_encode($data));
        $response = $this->apiClient->getResponse();

        /* @var Response $response */
        self::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode(), $response->getContent());

        self::assertNotEmpty($response->headers->get('Location'));
        self::assertEquals('http://localhost/api/v' . PlatformRequest::API_VERSION . '/product/' . $id, $response->headers->get('Location'));

        $this->apiClient->request('GET', '/api/v' . PlatformRequest::API_VERSION . '/product/' . $id);
        $this->assertSame(Response::HTTP_OK, $this->apiClient->getResponse()->getStatusCode());
    }

    public function testOneToManyInsert(): void
    {
        $id = Uuid::uuid4()->getHex();

        $data = ['id' => $id, 'name' => $id];

        $this->apiClient->request('POST', '/api/v' . PlatformRequest::API_VERSION . '/country', [], [], [], json_encode($data));
        $response = $this->apiClient->getResponse();
        self::assertSame(Response::HTTP_NO_CONTENT, $this->apiClient->getResponse()->getStatusCode(), $this->apiClient->getResponse()->getContent());
        self::assertNotEmpty($response->headers->get('Location'));
        self::assertEquals('http://localhost/api/v' . PlatformRequest::API_VERSION . '/country/' . $id, $response->headers->get('Location'));

        $this->apiClient->request('GET', '/api/v' . PlatformRequest::API_VERSION . '/country/' . $id);
        $this->assertSame(Response::HTTP_OK, $this->apiClient->getResponse()->getStatusCode(), $this->apiClient->getResponse()->getContent());

        $data = [
            'id' => $id,
            'name' => 'test_state',
            'shortCode' => 'test',
        ];

        $this->apiClient->request('POST', '/api/v' . PlatformRequest::API_VERSION . '/country/' . $id . '/states/', [], [], [], json_encode($data));
        $response = $this->apiClient->getResponse();
        $this->assertSame(Response::HTTP_NO_CONTENT, $this->apiClient->getResponse()->getStatusCode(), $this->apiClient->getResponse()->getContent());
        self::assertNotEmpty($response->headers->get('Location'));
        self::assertEquals('http://localhost/api/v' . PlatformRequest::API_VERSION . '/country-state/' . $id, $response->headers->get('Location'));

        $this->apiClient->request('GET', '/api/v' . PlatformRequest::API_VERSION . '/country/' . $id . '/states/');
        $responseData = json_decode($this->apiClient->getResponse()->getContent(), true);
        $this->assertSame(Response::HTTP_OK, $this->apiClient->getResponse()->getStatusCode());

        $this->assertArrayHasKey('data', $responseData);
        $this->assertCount(1, $responseData['data'], sprintf('Expected country %s has only one state', $id));

        $this->assertArrayHasKey('data', $responseData);
        $this->assertEquals(1, $responseData['meta']['total']);

        $this->assertSame($data['name'], $responseData['data'][0]['attributes']['name']);
        $this->assertSame($data['shortCode'], $responseData['data'][0]['attributes']['shortCode']);
    }

    public function testManyToOneInsert(): void
    {
        $id = Uuid::uuid4()->getHex();
        $manufacturer = Uuid::uuid4()->getHex();

        $data = [
            'id' => $id,
            'name' => $id,
            'tax' => ['name' => 'test', 'rate' => 10],
            'manufacturer' => ['name' => 'test'],
            'price' => ['gross' => 50, 'net' => 25],
        ];

        $this->apiClient->request('POST', '/api/v' . PlatformRequest::API_VERSION . '/product', [], [], [], json_encode($data));
        $response = $this->apiClient->getResponse();
        self::assertSame(Response::HTTP_NO_CONTENT, $this->apiClient->getResponse()->getStatusCode(), 'Create product failed id:' . $id);
        self::assertNotEmpty($response->headers->get('Location'));
        self::assertEquals('http://localhost/api/v' . PlatformRequest::API_VERSION . '/product/' . $id, $response->headers->get('Location'));

        $data = [
            'id' => $manufacturer,
            'name' => 'Manufacturer - 1',
            'link' => 'https://www.shopware.com',
        ];

        $this->apiClient->request('POST', '/api/v' . PlatformRequest::API_VERSION . '/product/' . $id . '/manufacturer', [], [], [], json_encode($data));
        $response = $this->apiClient->getResponse();
        $this->assertSame(Response::HTTP_NO_CONTENT, $this->apiClient->getResponse()->getStatusCode(), 'Create manufacturer over product failed id:' . $id . "\n" . $this->apiClient->getResponse()->getContent());
        self::assertNotEmpty($response->headers->get('Location'));
        self::assertEquals('http://localhost/api/v' . PlatformRequest::API_VERSION . '/product-manufacturer/' . $manufacturer, $response->headers->get('Location'));

        $this->apiClient->request('GET', '/api/v' . PlatformRequest::API_VERSION . '/product/' . $id . '/manufacturer');
        $responseData = json_decode($this->apiClient->getResponse()->getContent(), true);
        $this->assertSame(Response::HTTP_OK, $this->apiClient->getResponse()->getStatusCode(), 'Read manufacturer of product failed id: ' . $id . PHP_EOL . $this->apiClient->getResponse()->getContent());

        $this->assertArrayHasKey('data', $responseData, $this->apiClient->getResponse()->getContent());
        $this->assertArrayHasKey(0, $responseData['data'], $this->apiClient->getResponse()->getContent());
        $this->assertSame($data['name'], $responseData['data'][0]['attributes']['name']);
        $this->assertSame($data['link'], $responseData['data'][0]['attributes']['link']);
        $this->assertSame($data['id'], $responseData['data'][0]['id']);
    }

    public function testManyToManyInsert(): void
    {
        $id = Uuid::uuid4()->getHex();

        $data = [
            'id' => $id,
            'name' => $id,
            'tax' => ['name' => 'test', 'rate' => 10],
            'manufacturer' => ['name' => 'test'],
            'price' => ['gross' => 50, 'net' => 25],
        ];

        $this->apiClient->request('POST', '/api/v' . PlatformRequest::API_VERSION . '/product', [], [], [], json_encode($data));
        $response = $this->apiClient->getResponse();
        self::assertSame(Response::HTTP_NO_CONTENT, $this->apiClient->getResponse()->getStatusCode(), $this->apiClient->getResponse()->getContent());
        self::assertNotEmpty($response->headers->get('Location'));
        self::assertEquals('http://localhost/api/v' . PlatformRequest::API_VERSION . '/product/' . $id, $response->headers->get('Location'));

        $data = [
            'id' => $id,
            'name' => 'Category - 1',
        ];

        $this->apiClient->request('POST', '/api/v' . PlatformRequest::API_VERSION . '/product/' . $id . '/categories/', [], [], [], json_encode($data));
        $response = $this->apiClient->getResponse();
        $this->assertSame(Response::HTTP_NO_CONTENT, $this->apiClient->getResponse()->getStatusCode(), $this->apiClient->getResponse()->getContent());
        self::assertNotEmpty($response->headers->get('Location'));
        self::assertEquals('http://localhost/api/v1/category/' . $id, $response->headers->get('Location'));

        $this->apiClient->request('GET', '/api/v' . PlatformRequest::API_VERSION . '/product/' . $id . '/categories/');
        $responseData = json_decode($this->apiClient->getResponse()->getContent(), true);
        $this->assertSame(Response::HTTP_OK, $this->apiClient->getResponse()->getStatusCode());

        $this->assertArrayHasKey('data', $responseData);
        $this->assertSame($data['name'], $responseData['data'][0]['attributes']['name']);
        $this->assertSame($data['id'], $responseData['data'][0]['id']);
    }

    public function testDelete(): void
    {
        $id = Uuid::uuid4();

        $data = [
            'id' => $id->getHex(),
            'name' => $id->getHex(),
            'tax' => ['name' => 'test', 'rate' => 10],
            'manufacturer' => ['name' => 'test'],
            'price' => ['gross' => 50, 'net' => 25],
        ];

        $this->apiClient->request('POST', '/api/v' . PlatformRequest::API_VERSION . '/product', [], [], [], json_encode($data));
        $response = $this->apiClient->getResponse();
        self::assertSame(Response::HTTP_NO_CONTENT, $this->apiClient->getResponse()->getStatusCode(), $this->apiClient->getResponse()->getContent());
        self::assertNotEmpty($response->headers->get('Location'));
        self::assertEquals('http://localhost/api/v' . PlatformRequest::API_VERSION . '/product/' . $id->getHex(), $response->headers->get('Location'));

        $this->assertEntityExists('product', $id->getHex());

        $this->apiClient->request('DELETE', '/api/v' . PlatformRequest::API_VERSION . '/product/' . $id->getHex());
        self::assertSame(Response::HTTP_NO_CONTENT, $this->apiClient->getResponse()->getStatusCode(), $this->apiClient->getResponse()->getContent());

        $this->assertEntityNotExists('product', $id->getHex());
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

        $this->apiClient->request('POST', '/api/v' . PlatformRequest::API_VERSION . '/country', [], [], [], json_encode($data));
        $response = $this->apiClient->getResponse();
        self::assertSame(Response::HTTP_NO_CONTENT, $this->apiClient->getResponse()->getStatusCode(), $this->apiClient->getResponse()->getContent());
        self::assertNotEmpty($response->headers->get('Location'));
        self::assertEquals('http://localhost/api/v' . PlatformRequest::API_VERSION . '/country/' . $id->getHex(), $response->headers->get('Location'));

        $this->assertEntityExists('country', $id->getHex());
        $this->assertEntityExists('country-state', $stateId->getHex());

        $this->apiClient->request('DELETE', '/api/v' . PlatformRequest::API_VERSION . '/country/' . $id->getHex() . '/states/' . $stateId->getHex(), $data);
        self::assertSame(Response::HTTP_NO_CONTENT, $this->apiClient->getResponse()->getStatusCode(), $this->apiClient->getResponse()->getContent());

        $this->assertEntityExists('country', $id->getHex());
        $this->assertEntityNotExists('country-state', $stateId->getHex());
    }

    public function testDeleteManyToOne(): void
    {
        $country = Uuid::uuid4();
        $area = Uuid::uuid4();

        $data = [
            'id' => $country->getHex(),
            'name' => 'Country',
            'area' => ['id' => $area->getHex(), 'name' => 'Test'],
        ];

        $this->apiClient->request('POST', '/api/v' . PlatformRequest::API_VERSION . '/country', [], [], [], json_encode($data));
        $response = $this->apiClient->getResponse();
        self::assertSame(Response::HTTP_NO_CONTENT, $this->apiClient->getResponse()->getStatusCode(), $this->apiClient->getResponse()->getContent());
        self::assertNotEmpty($response->headers->get('Location'));
        self::assertEquals('http://localhost/api/v' . PlatformRequest::API_VERSION . '/country/' . $country->getHex(), $response->headers->get('Location'));

        $this->assertEntityExists('country', $country->getHex());
        $this->assertEntityExists('country-area', $area->getHex());

        $this->apiClient->request('DELETE', '/api/v' . PlatformRequest::API_VERSION . '/country/' . $country->getHex() . '/area/' . $area->getHex());
        self::assertSame(Response::HTTP_NO_CONTENT, $this->apiClient->getResponse()->getStatusCode(), $this->apiClient->getResponse()->getContent());

        $this->assertEntityExists('country', $country->getHex());
        $this->assertEntityNotExists('country-area', $area->getHex());
    }

    public function testDeleteManyToMany(): void
    {
        $id = Uuid::uuid4();
        $category = Uuid::uuid4();

        $data = [
            'id' => $id->getHex(),
            'name' => 'Test',
            'price' => ['gross' => 50, 'net' => 25],
            'tax' => ['name' => 'test', 'rate' => 10],
            'manufacturer' => ['name' => 'test'],
            'categories' => [
                ['id' => $category->getHex(), 'name' => 'Test'],
            ],
        ];

        $this->apiClient->request('POST', '/api/v' . PlatformRequest::API_VERSION . '/product', [], [], [], json_encode($data));
        $response = $this->apiClient->getResponse();
        self::assertSame(Response::HTTP_NO_CONTENT, $this->apiClient->getResponse()->getStatusCode(), $this->apiClient->getResponse()->getContent());
        self::assertNotEmpty($response->headers->get('Location'));
        self::assertEquals('http://localhost/api/v' . PlatformRequest::API_VERSION . '/product/' . $id->getHex(), $response->headers->get('Location'));

        $this->assertEntityExists('product', $id->getHex());
        $this->assertEntityExists('category', $category->getHex());

        $this->apiClient->request('DELETE', '/api/v' . PlatformRequest::API_VERSION . '/product/' . $id->getHex() . '/categories/' . $category->getHex());
        self::assertSame(Response::HTTP_NO_CONTENT, $this->apiClient->getResponse()->getStatusCode(), $this->apiClient->getResponse()->getContent());

        $a = self::$kernel->getContainer()->get(Connection::class)->executeQuery('SELECT * FROM product_category WHERE product_id = :pid AND category_id = :cid', ['pid' => $id->getBytes(), 'cid' => $category->getBytes()])->fetchAll();
        $this->assertEmpty($a);

        $this->assertEntityExists('product', $id->getHex());
        $this->assertEntityExists('category', $category->getHex());
    }

    public function testResponseDataTypeOnWrite(): void
    {
        $id = Uuid::uuid4();

        $data = ['id' => $id->getHex(), 'name' => $id->getHex(), 'rate' => 50];

        // create without response
        $this->apiClient->request('POST', '/api/v' . PlatformRequest::API_VERSION . '/tax', [], [], [], json_encode($data));
        $response = $this->apiClient->getResponse();
        self::assertSame(Response::HTTP_NO_CONTENT, $this->apiClient->getResponse()->getStatusCode(), $this->apiClient->getResponse()->getContent());
        self::assertNotEmpty($response->headers->get('Location'));
        self::assertEquals('http://localhost/api/v' . PlatformRequest::API_VERSION . '/tax/' . $id->getHex(), $response->headers->get('Location'));

        // update without response
        $this->apiClient->request('PATCH', '/api/v' . PlatformRequest::API_VERSION . '/tax/' . $id->getHex(), [], [], [], json_encode(['name' => 'foo']));
        $response = $this->apiClient->getResponse();
        self::assertSame(Response::HTTP_NO_CONTENT, $this->apiClient->getResponse()->getStatusCode());
        self::assertNotEmpty($response->headers->get('Location'));
        self::assertEquals('http://localhost/api/v' . PlatformRequest::API_VERSION . '/tax/' . $id->getHex(), $response->headers->get('Location'));

        // basic response
        $this->apiClient->request('PATCH', '/api/v' . PlatformRequest::API_VERSION . '/tax/' . $id->getHex() . '?_response=basic', [], [], [], json_encode(['name' => 'foo']));
        $response = $this->apiClient->getResponse();
        self::assertSame(Response::HTTP_OK, $this->apiClient->getResponse()->getStatusCode());
        self::assertNull($response->headers->get('Location'));

        // detail response
        $this->apiClient->request('PATCH', '/api/v' . PlatformRequest::API_VERSION . '/tax/' . $id->getHex() . '?_response=detail', [], [], [], json_encode(['name' => 'foo']));
        $response = $this->apiClient->getResponse();
        self::assertSame(Response::HTTP_OK, $this->apiClient->getResponse()->getStatusCode());
        self::assertNull($response->headers->get('Location'));

        // invalid response
        $this->apiClient->request('PATCH', '/api/v' . PlatformRequest::API_VERSION . '/tax/' . $id->getHex() . '?_response=does_not_exists', [], [], [], json_encode(['name' => 'foo']));
        $response = $this->apiClient->getResponse();
        self::assertSame(Response::HTTP_BAD_REQUEST, $this->apiClient->getResponse()->getStatusCode());
        self::assertNull($response->headers->get('Location'));
    }

    public function testSearch(): void
    {
        $id = Uuid::uuid4()->getHex();

        $data = [
            'id' => $id,
            'name' => 'Cotton Shirt',
            'tax' => ['name' => 'test', 'rate' => 10],
            'manufacturer' => ['name' => 'Shopware AG'],
            'price' => ['gross' => 50, 'net' => 25],
        ];

        $this->apiClient->request('POST', '/api/v' . PlatformRequest::API_VERSION . '/product', [], [], [], json_encode($data));
        $response = $this->apiClient->getResponse();
        self::assertSame(Response::HTTP_NO_CONTENT, $this->apiClient->getResponse()->getStatusCode(), $this->apiClient->getResponse()->getContent());
        self::assertNotEmpty($response->headers->get('Location'));
        self::assertEquals('http://localhost/api/v' . PlatformRequest::API_VERSION . '/product/' . $id, $response->headers->get('Location'));

        $data = [
            'offset' => 0,
            'size' => 5,
            'fetch-count' => Criteria::FETCH_COUNT_TOTAL,
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

        $this->apiClient->request('POST', '/api/v' . PlatformRequest::API_VERSION . '/search/product', [], [], [], json_encode($data));
        $response = $this->apiClient->getResponse();
        $content = json_decode($response->getContent(), true);

        self::assertEquals(1, $content['meta']['total']);
        self::assertEquals($id, $content['data'][0]['id']);

        $this->apiClient->request('DELETE', '/api/v' . PlatformRequest::API_VERSION . '/product/' . $id);
        $this->assertEquals(Response::HTTP_NO_CONTENT, $this->apiClient->getResponse()->getStatusCode());
    }

    public function testSimpleFilter()
    {
        $id = Uuid::uuid4()->getHex();

        $data = [
            'id' => $id,
            'name' => 'Wool Shirt',
            'tax' => ['name' => 'test', 'rate' => 10],
            'manufacturer' => ['name' => 'Shopware AG'],
            'price' => ['gross' => 8300, 'net' => 8300],
        ];

        $this->apiClient->request('POST', '/api/v' . PlatformRequest::API_VERSION . '/product', [], [], [], json_encode($data));
        $response = $this->apiClient->getResponse();
        self::assertSame(Response::HTTP_NO_CONTENT, $this->apiClient->getResponse()->getStatusCode(), $this->apiClient->getResponse()->getContent());
        self::assertNotEmpty($response->headers->get('Location'));
        self::assertEquals('http://localhost/api/v' . PlatformRequest::API_VERSION . '/product/' . $id, $response->headers->get('Location'));

        $data = [
            'filter' => [
                'product.id' => $id,
                'product.price' => 8300,
                'product.name' => 'Wool Shirt',
            ],
        ];

        $this->apiClient->request('GET', '/api/v' . PlatformRequest::API_VERSION . '/product', $data);
        $response = $this->apiClient->getResponse();
        $content = json_decode($response->getContent(), true);
        self::assertEquals(1, $content['meta']['total']);
        self::assertEquals($id, $content['data'][0]['id']);
    }
}
