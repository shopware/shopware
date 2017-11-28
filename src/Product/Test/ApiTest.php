<?php declare(strict_types=1);

namespace Shopware\Product\Tests;

use Doctrine\DBAL\Connection;
use Shopware\Rest\Test\ApiTestCase;
use Symfony\Bundle\FrameworkBundle\Client;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

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
                'uuid' => 'abc',
                'name' => 'test123',
                'the_unknown_field' => 'do nothing?',
                'taxUuid' => 'SWAG-TAX-UUID-1',
                'productManufacturer' => ['uuid' => 'SWAG-PRODUCT-MANUFACTURER-UUID-2'],
            ]
        );

        self::assertSame(
            200,
            $client->getResponse()->getStatusCode(),
            $client->getResponse()->getContent()
        );
    }
}
