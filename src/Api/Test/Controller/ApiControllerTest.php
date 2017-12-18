<?php declare(strict_types=1);

namespace Shopware\Api\Test\Controller;

use Ramsey\Uuid\Uuid;
use Shopware\Rest\Test\ApiTestCase;
use Symfony\Component\HttpFoundation\Response;

class ApiControllerTest extends ApiTestCase
{
    public function testInsert()
    {
        $uuid = Uuid::uuid4()->toString();

        $data = ['uuid' => $uuid, 'name' => $uuid];

        $client = $this->getClient();
        $client->request('POST', '/api/product', $data);
        $response = $client->getResponse();

        self::assertSame(200, $response->getStatusCode(), $response->getContent());

        $client->request('GET', '/api/product/' . $uuid);
        $this->assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
    }

    public function testOneToManyInsert()
    {
        $uuid = Uuid::uuid4()->toString();

        $data = ['uuid' => $uuid, 'name' => $uuid];

        $client = $this->getClient();
        $client->request('POST', '/api/product', $data);
        self::assertSame(200, $client->getResponse()->getStatusCode(), $client->getResponse()->getContent());

        $client->request('GET', '/api/product/' . $uuid);
        $this->assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode(), $client->getResponse()->getContent());

        $data = [
            'uuid' => $uuid,
            'price' => 19.99,
            'customerGroupUuid' => '3294e6f6-372b-415f-ac73-71cbc191548f',
        ];

        $client->request('POST', '/api/product/' . $uuid . '/prices/', $data);
        $this->assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        $client->request('GET', '/api/product/' . $uuid . '/prices/');
        $responseData = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        $this->assertArrayHasKey('data', $responseData);
        $this->assertCount(1, $responseData['data'], sprintf('Expected product %s has only one price', $uuid));

        $this->assertArrayHasKey('data', $responseData);
        $this->assertEquals(1, $responseData['total']);

        $this->assertSame($data['price'], $responseData['data'][0]['price']);
        $this->assertSame($data['customerGroupUuid'], $responseData['data'][0]['customerGroupUuid']);
    }

    public function testManyToOneInsert()
    {
        $uuid = Uuid::uuid4()->toString();

        $data = ['uuid' => $uuid, 'name' => $uuid];

        $client = $this->getClient();
        $client->request('POST', '/api/product', $data);
        self::assertSame(200, $client->getResponse()->getStatusCode(), 'Create product failed uuid:' . $uuid);

        $data = [
            'uuid' => $uuid,
            'name' => 'Manufacturer - 1',
            'link' => 'https://www.shopware.com',
        ];

        $client->request('POST', '/api/product/' . $uuid . '/manufacturer/', $data);
        $this->assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode(), 'Create manufacturer over product failed uuid:' . $uuid);

        $client->request('GET', '/api/product/' . $uuid . '/manufacturer/');
        $responseData = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode(), 'Read manufacturer of product failed uuid:' . $uuid);

        $this->assertArrayHasKey('data', $responseData);
        $this->assertSame($data['name'], $responseData['data'][0]['name']);
        $this->assertSame($data['link'], $responseData['data'][0]['link']);
        $this->assertSame($data['uuid'], $responseData['data'][0]['uuid']);
    }

    public function testManyToManyInsert()
    {
        $uuid = Uuid::uuid4()->toString();

        $data = ['uuid' => $uuid, 'name' => $uuid];

        $client = $this->getClient();
        $client->request('POST', '/api/product', $data);
        self::assertSame(200, $client->getResponse()->getStatusCode(), $client->getResponse()->getContent());

        $data = [
            'uuid' => $uuid,
            'name' => 'Category - 1',
        ];

        $client->request('POST', '/api/product/' . $uuid . '/categories/', $data);
        $this->assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        $client->request('GET', '/api/product/' . $uuid . '/categories/');
        $responseData = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        $this->assertArrayHasKey('data', $responseData);
        $this->assertSame($data['name'], $responseData['data'][0]['name']);
        $this->assertSame($data['uuid'], $responseData['data'][0]['uuid']);
    }
}
