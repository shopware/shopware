<?php declare(strict_types=1);

namespace Shopware\Product\Tests;

use Doctrine\DBAL\Connection;
use Shopware\Recovery\Common\HttpClient\Client;
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

    public function tearDown()
    {
        $this->connection->rollBack();
        parent::tearDown();
    }

    public function test_product_list_route()
    {
        $this->client->request('GET', '/api/product.json');

        self::assertSame(
            200,
            $this->client->getResponse()->getStatusCode(),
            $this->client->getResponse()->getContent()
        );
    }

    /**
     * @group test
     */
    public function test_product_insert_route()
    {
        $this->markTestSkipped('Not working currently');

        $this->client->request(
            'POST',
            '/api/product.json',
            [
                'uuid' => 'abc',
                'the_unknown_field' => 'do nothing?',
                'taxUuid' => 'SWAG-CONFIG-TAX-UUID-1',
                'productManufacturer' => ['uuid' => 'SWAG-PRODUCT-MANUFACTURER-UUID-2'],
                'mode' => 0,
                'lastStock' => 1,
                'crossbundlelook' => 1,
                'notification' => 0,
                'template' => 'foo',
            ]
        );

        self::assertSame(
            200,
            $this->client->getResponse()->getStatusCode(),
            $this->client->getResponse()->getContent()
        );
    }
}
