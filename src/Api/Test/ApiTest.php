<?php declare(strict_types=1);

namespace Shopware\Api\Test;

use Ramsey\Uuid\Uuid;
use Shopware\Rest\Test\ApiTestCase;

class ApiTest extends ApiTestCase
{
    public function testProductListRoute()
    {
        $client = $this->getClient();
        $client->request('GET', '/api/product');

        self::assertSame(
            200,
            $client->getResponse()->getStatusCode()
        );
    }

    public function testProductInsertRoute()
    {
        $client = $this->getClient();
        $client->request(
            'POST',
            '/api/product',
            [
                'id' => Uuid::uuid4()->toString(),
                'name' => 'test123',
                'the_unknown_field' => 'do nothing?',
                'taxId' => '49260353-68e3-4d9f-a695-e017d7a231b9',
                'productManufacturer' => ['id' => Uuid::uuid4()->toString(), 'name' => 'Test'],
            ]
        );

        self::assertSame(
            200,
            $client->getResponse()->getStatusCode(),
            $client->getResponse()->getContent()
        );
    }
}
