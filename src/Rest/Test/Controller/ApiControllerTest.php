<?php declare(strict_types=1);

namespace Shopware\Rest\Test\Controller;

use Doctrine\DBAL\Connection;
use Ramsey\Uuid\Uuid;
use Shopware\Defaults;
use Shopware\Rest\Test\ApiTestCase;
use Symfony\Component\HttpFoundation\Response;

class ApiControllerTest extends ApiTestCase
{
    public function testInsert()
    {
        $id = Uuid::uuid4()->toString();

        $data = ['id' => $id, 'name' => $id];

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
        $client->request('POST', '/api/product', $data);
        self::assertSame(200, $client->getResponse()->getStatusCode(), $client->getResponse()->getContent());

        $client->request('GET', '/api/product/' . $id);
        $this->assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode(), $client->getResponse()->getContent());

        $data = [
            'id' => $id,
            'price' => 19.99,
            'customerGroupId' => '3294e6f6-372b-415f-ac73-71cbc191548f',
        ];

        $client->request('POST', '/api/product/' . $id . '/prices/', $data);
        $this->assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        $client->request('GET', '/api/product/' . $id . '/prices/');
        $responseData = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        $this->assertArrayHasKey('data', $responseData);
        $this->assertCount(1, $responseData['data'], sprintf('Expected product %s has only one price', $id));

        $this->assertArrayHasKey('data', $responseData);
        $this->assertEquals(1, $responseData['total']);

        $this->assertSame($data['price'], $responseData['data'][0]['price']);
        $this->assertSame($data['customerGroupId'], $responseData['data'][0]['customerGroupId']);
    }

    public function testManyToOneInsert()
    {
        $id = Uuid::uuid4()->toString();
        $manufacturer = Uuid::uuid4()->toString();

        $data = ['id' => $id, 'name' => $id];

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

        $data = ['id' => $id, 'name' => $id];

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

        $data = ['id' => $id->toString(), 'name' => $id->toString()];

        $client = $this->getClient();
        $client->request('POST', '/api/product', $data);
        self::assertSame(200, $client->getResponse()->getStatusCode(), $client->getResponse()->getContent());

        /** @var Connection $connection */
        $connection = self::$container->get('dbal_connection');
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
        $priceId = Uuid::uuid4();

        $data = [
            'id' => $id->toString(),
            'name' => $id->toString(),
            'prices' => [
                ['id' => $priceId->toString(), 'price' => 10.99, 'customerGroupId' => Defaults::FALLBACK_CUSTOMER_GROUP],
            ],
        ];

        $client = $this->getClient();
        $client->request('POST', '/api/product', $data);
        self::assertSame(200, $client->getResponse()->getStatusCode(), $client->getResponse()->getContent());

        /** @var Connection $connection */
        $connection = self::$container->get('dbal_connection');
        $exists = $connection->fetchAll('SELECT * FROM product WHERE id = :id', ['id' => $id->getBytes()]);
        $this->assertNotEmpty($exists);

        $exists = $connection->fetchAll('SELECT * FROM product_price WHERE product_id = :id', ['id' => $id->getBytes()]);
        $this->assertNotEmpty($exists);

        $client = $this->getClient();
        $client->request('DELETE', '/api/product/' . $id->toString() . '/prices/' . $priceId->toString(), $data);
        self::assertSame(200, $client->getResponse()->getStatusCode(), $client->getResponse()->getContent());

        $exists = $connection->fetchAll('SELECT * FROM product_price WHERE product_id = :id', ['id' => $id->getBytes()]);
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
        $connection = self::$container->get('dbal_connection');
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
        $connection = self::$container->get('dbal_connection');
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
