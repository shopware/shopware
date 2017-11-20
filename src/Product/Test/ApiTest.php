<?php declare(strict_types=1);

namespace Shopware\Product\Tests;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Client;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ApiTest extends WebTestCase
{
    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var Client
     */
    private $client;

    public function setUp()
    {
        $this->client = self::createClient();
        $container = self::$kernel->getContainer();
        $this->connection = $container->get('dbal_connection');
        $this->connection->beginTransaction();
    }

    public function tearDown(): void
    {
        $this->connection->rollBack();
        parent::tearDown();
    }

    public function testProductListRoute()
    {
        $this->client->request('GET', '/api/product');

        self::assertSame(
            200,
            $this->client->getResponse()->getStatusCode()
        );
    }

    public function testProductInsertRoute()
    {
        $this->client->request(
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
            $this->client->getResponse()->getStatusCode()
        );
    }
}
