<?php declare(strict_types=1);

namespace Shopware\Api\Test\Controller;

use Ramsey\Uuid\Uuid;
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
}
