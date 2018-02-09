<?php declare(strict_types=1);

namespace Shopware\Rest\Test\Controller;

use Doctrine\DBAL\Connection;
use Ramsey\Uuid\Uuid;
use Shopware\Rest\Test\ApiTestCase;
use Symfony\Component\HttpFoundation\Response;

class ApiControllerTest extends ApiTestCase
{
    public function testInsert()
    {
        $id = Uuid::uuid4()->toString();

        $data = ['id' => $id, 'name' => $id, 'taxId' => '49260353-68e3-4d9f-a695-e017d7a231b9', 'manufacturer' => ['name' => 'test'], 'price' => 10];

        $client = $this->getClient();
        $client->request('POST', '/api/product', $data);
        $response = $client->getResponse();

        self::assertSame(200, $response->getStatusCode(), $response->getContent());

        $client->request('GET', '/api/product/' . $id);
        $this->assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
    }

    public function testOneToManyInsert()
    {
        $id = Uuid::uuid4()->toString();

        $data = ['id' => $id, 'name' => $id];

        $client = $this->getClient();
        $client->request('POST', '/api/country', $data);
        self::assertSame(200, $client->getResponse()->getStatusCode(), $client->getResponse()->getContent());

        $client->request('GET', '/api/country/' . $id);
        $this->assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode(), $client->getResponse()->getContent());

        $data = [
            'id' => $id,
            'name' => 'test_state',
            'shortCode' => 'test',
        ];

        $client->request('POST', '/api/country/' . $id . '/states/', $data);
        $this->assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        $client->request('GET', '/api/country/' . $id . '/states/');
        $responseData = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        $this->assertArrayHasKey('data', $responseData);
        $this->assertCount(1, $responseData['data'], sprintf('Expected country %s has only one state', $id));

        $this->assertArrayHasKey('data', $responseData);
        $this->assertEquals(1, $responseData['total']);

        $this->assertSame($data['name'], $responseData['data'][0]['name']);
        $this->assertSame($data['shortCode'], $responseData['data'][0]['shortCode']);
    }

    public function testManyToOneInsert()
    {
        $id = Uuid::uuid4()->toString();
        $manufacturer = Uuid::uuid4()->toString();

        $data = ['id' => $id, 'name' => $id, 'taxId' => '49260353-68e3-4d9f-a695-e017d7a231b9', 'manufacturer' => ['name' => 'test'], 'price' => 10];

        $client = $this->getClient();
        $client->request('POST', '/api/product', $data);
        self::assertSame(200, $client->getResponse()->getStatusCode(), 'Create product failed id:' . $id);

        $data = [
            'id' => $manufacturer,
            'name' => 'Manufacturer - 1',
            'link' => 'https://www.shopware.com',
        ];

        $client->request('POST', '/api/product/' . $id . '/manufacturer/', $data);
        $this->assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode(), 'Create manufacturer over product failed id:' . $id . "\n" . $client->getResponse()->getContent());

        $client->request('GET', '/api/product/' . $id . '/manufacturer/');
        $responseData = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode(), 'Read manufacturer of product failed id:' . $id);

        $this->assertArrayHasKey('data', $responseData);
        $this->assertSame($data['name'], $responseData['data'][0]['name']);
        $this->assertSame($data['link'], $responseData['data'][0]['link']);
        $this->assertSame($data['id'], $responseData['data'][0]['id']);
    }

    public function testManyToManyInsert()
    {
        $id = Uuid::uuid4()->toString();

        $data = ['id' => $id, 'name' => $id, 'taxId' => '49260353-68e3-4d9f-a695-e017d7a231b9', 'manufacturer' => ['name' => 'test'], 'price' => 10];

        $client = $this->getClient();
        $client->request('POST', '/api/product', $data);
        self::assertSame(200, $client->getResponse()->getStatusCode(), $client->getResponse()->getContent());

        $data = [
            'id' => $id,
            'name' => 'Category - 1',
        ];

        $client->request('POST', '/api/product/' . $id . '/categories/', $data);
        $this->assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        $client->request('GET', '/api/product/' . $id . '/categories/');
        $responseData = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        $this->assertArrayHasKey('data', $responseData);
        $this->assertSame($data['name'], $responseData['data'][0]['name']);
        $this->assertSame($data['id'], $responseData['data'][0]['id']);
    }

    public function testDelete()
    {
        $id = Uuid::uuid4();

        $data = ['id' => $id->toString(), 'name' => $id->toString(), 'taxId' => '49260353-68e3-4d9f-a695-e017d7a231b9', 'manufacturer' => ['name' => 'test'], 'price' => 10];

        $client = $this->getClient();
        $client->request('POST', '/api/product', $data);
        self::assertSame(200, $client->getResponse()->getStatusCode(), $client->getResponse()->getContent());

        /** @var Connection $connection */
        $connection = self::$container->get(Connection::class);
        $exists = $connection->fetchAll('SELECT * FROM product WHERE id = :id', ['id' => $id->getBytes()]);
        $this->assertNotEmpty($exists);

        $client = $this->getClient();
        $client->request('DELETE', '/api/product/' . $id->toString());
        self::assertSame(200, $client->getResponse()->getStatusCode(), $client->getResponse()->getContent());

        $exists = $connection->fetchAll('SELECT * FROM product WHERE id = :id', ['id' => $id->getBytes()]);
        $this->assertEmpty($exists);
    }

    public function testDeleteOneToMany()
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
        $client->request('POST', '/api/country', $data);
        self::assertSame(200, $client->getResponse()->getStatusCode(), $client->getResponse()->getContent());

        /** @var Connection $connection */
        $connection = self::$container->get(Connection::class);
        $exists = $connection->fetchAll('SELECT * FROM country WHERE id = :id', ['id' => $id->getBytes()]);
        $this->assertNotEmpty($exists);

        $exists = $connection->fetchAll('SELECT * FROM country_state WHERE country_id = :id', ['id' => $id->getBytes()]);
        $this->assertNotEmpty($exists);

        $client = $this->getClient();
        $client->request('DELETE', '/api/country/' . $id->toString() . '/states/' . $stateId->toString(), $data);
        self::assertSame(200, $client->getResponse()->getStatusCode(), $client->getResponse()->getContent());

        $exists = $connection->fetchAll('SELECT * FROM country_state WHERE country_id = :id', ['id' => $id->getBytes()]);
        $this->assertEmpty($exists);
    }

    public function testDeleteManyToOne()
    {
        $country = Uuid::uuid4();
        $area = Uuid::uuid4();

        $data = [
            'id' => $country->toString(),
            'name' => 'Country',
            'area' => ['id' => $area->toString(), 'name' => 'Test'],
        ];

        $client = $this->getClient();
        $client->request('POST', '/api/country', $data);
        self::assertSame(200, $client->getResponse()->getStatusCode(), $client->getResponse()->getContent());

        /** @var Connection $connection */
        $connection = self::$container->get(Connection::class);
        $exists = $connection->fetchAll('SELECT * FROM country WHERE id = :id', ['id' => $country->getBytes()]);
        $this->assertNotEmpty($exists);

        $exists = $connection->fetchAll('SELECT * FROM country_area WHERE id = :id', ['id' => $area->getBytes()]);
        $this->assertNotEmpty($exists);

        $client = $this->getClient();
        $client->request('DELETE', '/api/country/' . $country->toString() . '/area/' . $area->toString());
        self::assertSame(200, $client->getResponse()->getStatusCode(), $client->getResponse()->getContent());

        $exists = $connection->fetchAll('SELECT * FROM country WHERE id = :id', ['id' => $country->getBytes()]);
        $this->assertNotEmpty($exists);

        $exists = $connection->fetchAll('SELECT * FROM country_area WHERE id = :id', ['id' => $area->getBytes()]);
        $this->assertEmpty($exists);
    }

    public function testDeleteManyToMany()
    {
        $id = Uuid::uuid4();
        $category = Uuid::uuid4();

        $data = [
            'id' => $id->toString(),
            'name' => 'Test',
            'price' => 10,
            'taxId' => '49260353-68e3-4d9f-a695-e017d7a231b9',
            'manufacturer' => ['name' => 'test'],
            'categories' => [
                ['category' => [
                    'id' => $category->toString(), 'name' => 'Test',
                ]],
            ],
        ];

        $client = $this->getClient();
        $client->request('POST', '/api/product', $data);
        self::assertSame(200, $client->getResponse()->getStatusCode(), $client->getResponse()->getContent());

        /** @var Connection $connection */
        $connection = self::$container->get(Connection::class);
        $exists = $connection->fetchAll('SELECT * FROM product WHERE id = :id', ['id' => $id->getBytes()]);
        $this->assertNotEmpty($exists);

        $exists = $connection->fetchAll('SELECT * FROM category WHERE id = :id', ['id' => $category->getBytes()]);
        $this->assertNotEmpty($exists);

        $client = $this->getClient();
        $client->request('DELETE', '/api/product/' . $id->toString() . '/categories/' . $category->toString());
        self::assertSame(200, $client->getResponse()->getStatusCode(), $client->getResponse()->getContent());

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
}
