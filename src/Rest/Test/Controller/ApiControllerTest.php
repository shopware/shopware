<?php declare(strict_types=1);

namespace Shopware\Rest\Test\Controller;

use Doctrine\DBAL\Connection;
use Ramsey\Uuid\Uuid;
use Shopware\Rest\Test\ApiTestCase;
use Symfony\Component\HttpFoundation\Response;

class ApiControllerTest extends ApiTestCase
{
    public function testInsert(): void
    {
        $id = Uuid::uuid4()->toString();

        $data = [
            'id' => $id,
            'name' => $id,
            'tax' => ['name' => 'test', 'rate' => 10],
            'manufacturer' => ['name' => 'test'],
            'price' => ['gross' => 50, 'net' => 25],
        ];

        $client = $this->getClient();
        $client->request('POST', '/api/product', [], [], [], json_encode($data));
        $response = $client->getResponse();

        /* @var Response $response */
        self::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode(), $response->getContent());

        self::assertNotEmpty($response->headers->get('Location'));
        self::assertEquals('http://localhost/api/product/' . $id, $response->headers->get('Location'));

        $client->request('GET', '/api/product/' . $id);
        $this->assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
    }

    public function testOneToManyInsert(): void
    {
        $id = Uuid::uuid4()->toString();

        $data = ['id' => $id, 'name' => $id];

        $client = $this->getClient();
        $client->request('POST', '/api/country', [], [], [], json_encode($data));
        $response = $client->getResponse();
        self::assertSame(Response::HTTP_NO_CONTENT, $client->getResponse()->getStatusCode(), $client->getResponse()->getContent());
        self::assertNotEmpty($response->headers->get('Location'));
        self::assertEquals('http://localhost/api/country/' . $id, $response->headers->get('Location'));

        $client->request('GET', '/api/country/' . $id);
        $this->assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode(), $client->getResponse()->getContent());

        $data = [
            'id' => $id,
            'name' => 'test_state',
            'shortCode' => 'test',
        ];

        $client->request('POST', '/api/country/' . $id . '/states/', [], [], [], json_encode($data));
        $response = $client->getResponse();
        $this->assertSame(Response::HTTP_NO_CONTENT, $client->getResponse()->getStatusCode(), $client->getResponse()->getContent());
        self::assertNotEmpty($response->headers->get('Location'));
        self::assertEquals('http://localhost/api/country-state/' . $id, $response->headers->get('Location'));

        $client->request('GET', '/api/country/' . $id . '/states/');
        $responseData = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        $this->assertArrayHasKey('data', $responseData);
        $this->assertCount(1, $responseData['data'], sprintf('Expected country %s has only one state', $id));

        $this->assertArrayHasKey('data', $responseData);
        $this->assertEquals(1, $responseData['meta']['total']);

        $this->assertSame($data['name'], $responseData['data'][0]['attributes']['name']);
        $this->assertSame($data['shortCode'], $responseData['data'][0]['attributes']['shortCode']);
    }

    public function testManyToOneInsert(): void
    {
        $id = Uuid::uuid4()->toString();
        $manufacturer = Uuid::uuid4()->toString();

        $data = [
            'id' => $id,
            'name' => $id,
            'tax' => ['name' => 'test', 'rate' => 10],
            'manufacturer' => ['name' => 'test'],
            'price' => ['gross' => 50, 'net' => 25],
        ];

        $client = $this->getClient();
        $client->request('POST', '/api/product', [], [], [], json_encode($data));
        $response = $client->getResponse();
        self::assertSame(Response::HTTP_NO_CONTENT, $client->getResponse()->getStatusCode(), 'Create product failed id:' . $id);
        self::assertNotEmpty($response->headers->get('Location'));
        self::assertEquals('http://localhost/api/product/' . $id, $response->headers->get('Location'));

        $data = [
            'id' => $manufacturer,
            'name' => 'Manufacturer - 1',
            'link' => 'https://www.shopware.com',
        ];

        $client->request('POST', '/api/product/' . $id . '/manufacturer', [], [], [], json_encode($data));
        $response = $client->getResponse();
        $this->assertSame(Response::HTTP_NO_CONTENT, $client->getResponse()->getStatusCode(), 'Create manufacturer over product failed id:' . $id . "\n" . $client->getResponse()->getContent());
        self::assertNotEmpty($response->headers->get('Location'));
        self::assertEquals('http://localhost/api/product-manufacturer/' . $manufacturer, $response->headers->get('Location'));

        $client->request('GET', '/api/product/' . $id . '/manufacturer');
        $responseData = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode(), 'Read manufacturer of product failed id: ' . $id . PHP_EOL . $client->getResponse()->getContent());

        $this->assertArrayHasKey('data', $responseData, $client->getResponse()->getContent());
        $this->assertArrayHasKey(0, $responseData['data'], $client->getResponse()->getContent());
        $this->assertSame($data['name'], $responseData['data'][0]['attributes']['name']);
        $this->assertSame($data['link'], $responseData['data'][0]['attributes']['link']);
        $this->assertSame($data['id'], $responseData['data'][0]['id']);
    }

    public function testManyToManyInsert(): void
    {
        $id = Uuid::uuid4()->toString();

        $data = [
            'id' => $id,
            'name' => $id,
            'tax' => ['name' => 'test', 'rate' => 10],
            'manufacturer' => ['name' => 'test'],
            'price' => ['gross' => 50, 'net' => 25],
        ];

        $client = $this->getClient();
        $client->request('POST', '/api/product', [], [], [], json_encode($data));
        $response = $client->getResponse();
        self::assertSame(Response::HTTP_NO_CONTENT, $client->getResponse()->getStatusCode(), $client->getResponse()->getContent());
        self::assertNotEmpty($response->headers->get('Location'));
        self::assertEquals('http://localhost/api/product/' . $id, $response->headers->get('Location'));

        $data = [
            'id' => $id,
            'name' => 'Category - 1',
        ];

        $client->request('POST', '/api/product/' . $id . '/categories/', [], [], [], json_encode($data));
        $response = $client->getResponse();
        $this->assertSame(Response::HTTP_NO_CONTENT, $client->getResponse()->getStatusCode(), $client->getResponse()->getContent());
        self::assertNotEmpty($response->headers->get('Location'));
        self::assertEquals('http://localhost/api/category/' . $id, $response->headers->get('Location'));

        $client->request('GET', '/api/product/' . $id . '/categories/');
        $responseData = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        $this->assertArrayHasKey('data', $responseData);
        $this->assertSame($data['name'], $responseData['data'][0]['attributes']['name']);
        $this->assertSame($data['id'], $responseData['data'][0]['id']);
    }

    public function testDelete(): void
    {
        $id = Uuid::uuid4();

        $data = [
            'id' => $id->toString(),
            'name' => $id->toString(),
            'tax' => ['name' => 'test', 'rate' => 10],
            'manufacturer' => ['name' => 'test'],
            'price' => ['gross' => 50, 'net' => 25],
        ];

        $client = $this->getClient();
        $client->request('POST', '/api/product', [], [], [], json_encode($data));
        $response = $client->getResponse();
        self::assertSame(Response::HTTP_NO_CONTENT, $client->getResponse()->getStatusCode(), $client->getResponse()->getContent());
        self::assertNotEmpty($response->headers->get('Location'));
        self::assertEquals('http://localhost/api/product/' . $id->toString(), $response->headers->get('Location'));

        /** @var Connection $connection */
        $connection = self::$container->get(Connection::class);
        $exists = $connection->fetchAll('SELECT * FROM product WHERE id = :id', ['id' => $id->getBytes()]);
        $this->assertNotEmpty($exists);

        $client = $this->getClient();
        $client->request('DELETE', '/api/product/' . $id->toString());
        self::assertSame(Response::HTTP_NO_CONTENT, $client->getResponse()->getStatusCode(), $client->getResponse()->getContent());

        $exists = $connection->fetchAll('SELECT * FROM product WHERE id = :id', ['id' => $id->getBytes()]);
        $this->assertEmpty($exists);
    }

    public function testDeleteOneToMany(): void
    {
        $id = Uuid::uuid4();
        $stateId = Uuid::uuid4();

        $data = [
            'id' => $id->toString(),
            'name' => $id->toString(),
            'states' => [
                ['id' => $stateId->toString(), 'shortCode' => 'test', 'name' => 'test'],
            ],
        ];

        $client = $this->getClient();
        $client->request('POST', '/api/country', [], [], [], json_encode($data));
        $response = $client->getResponse();
        self::assertSame(Response::HTTP_NO_CONTENT, $client->getResponse()->getStatusCode(), $client->getResponse()->getContent());
        self::assertNotEmpty($response->headers->get('Location'));
        self::assertEquals('http://localhost/api/country/' . $id->toString(), $response->headers->get('Location'));

        /** @var Connection $connection */
        $connection = self::$container->get(Connection::class);
        $exists = $connection->fetchAll('SELECT * FROM country WHERE id = :id', ['id' => $id->getBytes()]);
        $this->assertNotEmpty($exists);

        $exists = $connection->fetchAll('SELECT * FROM country_state WHERE country_id = :id', ['id' => $id->getBytes()]);
        $this->assertNotEmpty($exists);

        $client = $this->getClient();
        $client->request('DELETE', '/api/country/' . $id->toString() . '/states/' . $stateId->toString(), $data);
        self::assertSame(Response::HTTP_NO_CONTENT, $client->getResponse()->getStatusCode(), $client->getResponse()->getContent());

        $exists = $connection->fetchAll('SELECT * FROM country_state WHERE country_id = :id', ['id' => $id->getBytes()]);
        $this->assertEmpty($exists);
    }

    public function testDeleteManyToOne(): void
    {
        $country = Uuid::uuid4();
        $area = Uuid::uuid4();

        $data = [
            'id' => $country->toString(),
            'name' => 'Country',
            'area' => ['id' => $area->toString(), 'name' => 'Test'],
        ];

        $client = $this->getClient();
        $client->request('POST', '/api/country', [], [], [], json_encode($data));
        $response = $client->getResponse();
        self::assertSame(Response::HTTP_NO_CONTENT, $client->getResponse()->getStatusCode(), $client->getResponse()->getContent());
        self::assertNotEmpty($response->headers->get('Location'));
        self::assertEquals('http://localhost/api/country/' . $country->toString(), $response->headers->get('Location'));

        /** @var Connection $connection */
        $connection = self::$container->get(Connection::class);
        $exists = $connection->fetchAll('SELECT * FROM country WHERE id = :id', ['id' => $country->getBytes()]);
        $this->assertNotEmpty($exists);

        $exists = $connection->fetchAll('SELECT * FROM country_area WHERE id = :id', ['id' => $area->getBytes()]);
        $this->assertNotEmpty($exists);

        $client = $this->getClient();
        $client->request('DELETE', '/api/country/' . $country->toString() . '/area/' . $area->toString());
        self::assertSame(Response::HTTP_NO_CONTENT, $client->getResponse()->getStatusCode(), $client->getResponse()->getContent());

        $exists = $connection->fetchAll('SELECT * FROM country WHERE id = :id', ['id' => $country->getBytes()]);
        $this->assertNotEmpty($exists);

        $exists = $connection->fetchAll('SELECT * FROM country_area WHERE id = :id', ['id' => $area->getBytes()]);
        $this->assertEmpty($exists);
    }

    public function testDeleteManyToMany(): void
    {
        $id = Uuid::uuid4();
        $category = Uuid::uuid4();

        $data = [
            'id' => $id->toString(),
            'name' => 'Test',
            'price' => ['gross' => 50, 'net' => 25],
            'tax' => ['name' => 'test', 'rate' => 10],
            'manufacturer' => ['name' => 'test'],
            'categories' => [
                ['id' => $category->toString(), 'name' => 'Test'],
            ],
        ];

        $client = $this->getClient();
        $client->request('POST', '/api/product', [], [], [], json_encode($data));
        $response = $client->getResponse();
        self::assertSame(Response::HTTP_NO_CONTENT, $client->getResponse()->getStatusCode(), $client->getResponse()->getContent());
        self::assertNotEmpty($response->headers->get('Location'));
        self::assertEquals('http://localhost/api/product/' . $id->toString(), $response->headers->get('Location'));

        /** @var Connection $connection */
        $connection = self::$container->get(Connection::class);
        $exists = $connection->fetchAll('SELECT * FROM product WHERE id = :id', ['id' => $id->getBytes()]);
        $this->assertNotEmpty($exists);

        $exists = $connection->fetchAll('SELECT * FROM category WHERE id = :id', ['id' => $category->getBytes()]);
        $this->assertNotEmpty($exists);

        $client = $this->getClient();
        $client->request('DELETE', '/api/product/' . $id->toString() . '/categories/' . $category->toString());
        self::assertSame(Response::HTTP_NO_CONTENT, $client->getResponse()->getStatusCode(), $client->getResponse()->getContent());

        $exists = $connection->fetchAll(
            'SELECT * FROM product_category WHERE product_id = :product AND category_id = :category',
            ['category' => $category->getBytes(), 'product' => $id->toString()]
        );
        $this->assertEmpty($exists);

        $exists = $connection->fetchAll('SELECT * FROM product WHERE id = :id', ['id' => $id->getBytes()]);
        $this->assertNotEmpty($exists);

        $exists = $connection->fetchAll('SELECT * FROM category WHERE id = :id', ['id' => $category->getBytes()]);
        $this->assertNotEmpty($exists);
    }

    public function testResponseDataTypeOnWrite(): void
    {
        $id = Uuid::uuid4();

        $data = ['id' => $id->toString(), 'name' => $id->toString(), 'rate' => 50];

        $client = $this->getClient();

        // create without response
        $client->request('POST', '/api/tax', [], [], [], json_encode($data));
        $response = $client->getResponse();
        self::assertSame(Response::HTTP_NO_CONTENT, $client->getResponse()->getStatusCode(), $client->getResponse()->getContent());
        self::assertNotEmpty($response->headers->get('Location'));
        self::assertEquals('http://localhost/api/tax/' . $id->toString(), $response->headers->get('Location'));

        // update without response
        $client->request('PATCH', '/api/tax/' . $id->toString(), [], [], [], json_encode(['name' => 'foo']));
        $response = $client->getResponse();
        self::assertSame(Response::HTTP_NO_CONTENT, $client->getResponse()->getStatusCode());
        self::assertNotEmpty($response->headers->get('Location'));
        self::assertEquals('http://localhost/api/tax/' . $id->toString(), $response->headers->get('Location'));

        // basic response
        $client->request('PATCH', '/api/tax/' . $id->toString() . '?_response=basic', [], [], [], json_encode(['name' => 'foo']));
        $response = $client->getResponse();
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        self::assertNull($response->headers->get('Location'));

        // detail response
        $client->request('PATCH', '/api/tax/' . $id->toString() . '?_response=detail', [], [], [], json_encode(['name' => 'foo']));
        $response = $client->getResponse();
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        self::assertNull($response->headers->get('Location'));

        // invalid response
        $client->request('PATCH', '/api/tax/' . $id->toString() . '?_response=does_not_exists', [], [], [], json_encode(['name' => 'foo']));
        $response = $client->getResponse();
        self::assertSame(Response::HTTP_BAD_REQUEST, $client->getResponse()->getStatusCode());
        self::assertNull($response->headers->get('Location'));
    }
}
