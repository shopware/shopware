<?php

namespace Shopware\Api\Test;

use Doctrine\DBAL\Connection;
use Shopware\Api\Controller\SyncController;
use Shopware\Category\Definition\CategoryDefinition;
use Shopware\Product\Definition\ProductDefinition;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Bundle\FrameworkBundle\Client;

class SyncApiTest extends WebTestCase
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

    public function testMultipleProductInsert()
    {
        $data = [
            [
                'action' => SyncController::ACTION_UPSERT,
                'entity' => ProductDefinition::getEntityName(),
                'payload' => [
                    'uuid' => 'CREATE-1',
                    'name' => 'CREATE-1'
                ]
            ],
            [
                'action' => SyncController::ACTION_UPSERT,
                'entity' => ProductDefinition::getEntityName(),
                'payload' => [
                    'uuid' => 'CREATE-2',
                    'name' => 'CREATE-2'
                ]
            ]
        ];

        $this->client->request('POST', '/api/sync', $data);
        $response = $this->client->getResponse();

        self::assertSame(200, $response->getStatusCode());

        $count = $this->connection->fetchAll("SELECT uuid FROM product WHERE uuid IN ('CREATE-1', 'CREATE-2')");

        self::assertCount(2, $count);
    }

    public function testInsertAndUpdateSameEntity()
    {
        $data = [
            [
                'action' => SyncController::ACTION_UPSERT,
                'entity' => ProductDefinition::getEntityName(),
                'payload' => [
                    'uuid' => 'CREATE-1',
                    'active' => true,
                    'name' => 'CREATE-1'
                ]
            ],
            [
                'action' => SyncController::ACTION_UPSERT,
                'entity' => ProductDefinition::getEntityName(),
                'payload' => [
                    'uuid' => 'CREATE-1',
                    'active' => false
                ]
            ],
        ];

        $this->client->request('POST', '/api/sync', $data);
        $response = $this->client->getResponse();

        self::assertSame(200, $response->getStatusCode());

        $result = $this->connection->fetchAll("SELECT uuid, active FROM product WHERE uuid IN ('CREATE-1')");
        self::assertCount(1, $result);

        $row = array_shift($result);
        self::assertSame('0', $row['active']);
    }

    public function testInsertAndLinkEntities()
    {
        $data = [
            [
                'action' => SyncController::ACTION_UPSERT,
                'entity' => CategoryDefinition::getEntityName(),
                'payload' => [
                    'uuid' => 'CAT-1',
                    'name' => 'CAT-1'
                ]
            ],
            [
                'action' => SyncController::ACTION_UPSERT,
                'entity' => ProductDefinition::getEntityName(),
                'payload' => [
                    'uuid' => 'PROD-1',
                    'name' => 'PROD-1',
                    'categories' => [
                        ['categoryUuid' => 'CAT-1']
                    ]
                ]
            ],
        ];

        $this->client->request('POST', '/api/sync', $data);

        $response = $this->client->getResponse();
        self::assertSame(200, $response->getStatusCode());

        $result = $this->connection->fetchAll("SELECT uuid, active FROM product WHERE uuid IN ('PROD-1')");
        self::assertCount(1, $result);

        $result = $this->connection->fetchAll("SELECT * FROM product_category WHERE product_uuid = 'PROD-1'");
        self::assertCount(1, $result);
    }

    public function testNestedInsertAndLinkAfter()
    {
        $data = [
            [
                'action' => SyncController::ACTION_UPSERT,
                'entity' => ProductDefinition::getEntityName(),
                'payload' => [
                    'uuid' => 'PROD-1',
                    'name' => 'PROD-1',
                    'categories' => [
                        [
                            'category' => [
                                'uuid' => 'NESTED-CAT-1',
                                'name' => 'NESTED-CAT-1'
                            ]
                        ]
                    ]
                ]
            ],
            [
                'action' => SyncController::ACTION_UPSERT,
                'entity' => ProductDefinition::getEntityName(),
                'payload' => [
                    'uuid' => 'PROD-2',
                    'name' => 'PROD-2',
                    'categories' => [
                        ['categoryUuid' => 'NESTED-CAT-1']
                    ]
                ]
            ]
        ];

        $this->client->request('POST', '/api/sync', $data);

        $response = $this->client->getResponse();
        self::assertSame(200, $response->getStatusCode());

        $result = $this->connection->fetchAll("SELECT uuid, active FROM product WHERE uuid IN ('PROD-1', 'PROD-2')");
        self::assertCount(2, $result);

        $result = $this->connection->fetchAll("SELECT * FROM product_category WHERE product_uuid IN ('PROD-1', 'PROD-2')");
        self::assertCount(2, $result);
    }
}
