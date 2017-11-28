<?php declare(strict_types=1);

namespace Shopware\Api\Test;

use Shopware\Api\Controller\SyncController;
use Shopware\Category\Definition\CategoryDefinition;
use Shopware\Product\Definition\ProductDefinition;
use Shopware\Rest\Test\ApiTestCase;
use Symfony\Component\HttpFoundation\Response;

class SyncApiTest extends ApiTestCase
{
    public function setUp()
    {
        parent::setUp();

        $this->markTestSkipped('Entity deletion not implemented yet, transactions do not work.');
    }

    public function testMultipleProductInsert()
    {
        $data = [
            [
                'action' => SyncController::ACTION_UPSERT,
                'entity' => ProductDefinition::getEntityName(),
                'payload' => [
                    'uuid' => 'CREATE-1',
                    'name' => 'CREATE-1',
                ],
            ],
            [
                'action' => SyncController::ACTION_UPSERT,
                'entity' => ProductDefinition::getEntityName(),
                'payload' => [
                    'uuid' => 'CREATE-2',
                    'name' => 'CREATE-2',
                ],
            ],
        ];

        $client = $this->getClient();
        $client->request('POST', '/api/sync', $data);
        $response = $client->getResponse();

        self::assertSame(200, $response->getStatusCode(), $response->getContent());

        $client->request('GET', '/api/product/CREATE-1');
        $this->assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        $client->request('GET', '/api/product/CREATE-2');
        $this->assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
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
                    'name' => 'CREATE-1',
                ],
            ],
            [
                'action' => SyncController::ACTION_UPSERT,
                'entity' => ProductDefinition::getEntityName(),
                'payload' => [
                    'uuid' => 'CREATE-1',
                    'active' => false,
                ],
            ],
        ];

        $client = $this->getClient();
        $client->request('POST', '/api/sync', $data);
        self::assertSame(200, $client->getResponse()->getStatusCode(), $client->getResponse()->getContent());

        $client->request('GET', '/api/product/CREATE-1');
        $this->assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        $responseData = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals(false, $responseData['data']['active']);
    }

    public function testInsertAndLinkEntities()
    {
        $data = [
            [
                'action' => SyncController::ACTION_UPSERT,
                'entity' => CategoryDefinition::getEntityName(),
                'payload' => [
                    'uuid' => 'CAT-1',
                    'name' => 'CAT-1',
                ],
            ],
            [
                'action' => SyncController::ACTION_UPSERT,
                'entity' => ProductDefinition::getEntityName(),
                'payload' => [
                    'uuid' => 'PROD-1',
                    'name' => 'PROD-1',
                    'categories' => [
                        ['categoryUuid' => 'CAT-1'],
                    ],
                ],
            ],
        ];

        $client = $this->getClient();
        $client->request('POST', '/api/sync', $data);

        $response = $client->getResponse();
        self::assertSame(200, $response->getStatusCode());

        $client->request('GET', '/api/product/PROD-1');
        $responseData = json_decode($client->getResponse()->getContent(), true);

        $this->assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        $this->assertContains('CAT-1', $responseData['data']['categoryUuids']);
        $this->assertCount(1, $responseData['data']['categoryUuids'], 'Category Uuids should not contain: ' . print_r(array_diff($responseData['data']['categoryUuids'], ['CAT-1']), true));
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
                                'name' => 'NESTED-CAT-1',
                            ],
                        ],
                    ],
                ],
            ],
            [
                'action' => SyncController::ACTION_UPSERT,
                'entity' => ProductDefinition::getEntityName(),
                'payload' => [
                    'uuid' => 'PROD-2',
                    'name' => 'PROD-2',
                    'categories' => [
                        ['categoryUuid' => 'NESTED-CAT-1'],
                    ],
                ],
            ],
        ];

        $client = $this->getClient();
        $client->request('POST', '/api/sync', $data);

        $client->request('GET', '/api/product/PROD-1');
        $responseData = json_decode($client->getResponse()->getContent(), true);
        $this->assertContains('CAT-1', $responseData['data']['categoryUuids']);
        $this->assertContains('NESTED-CAT-1', $responseData['data']['categoryUuids']);
        $this->assertCount(2, $responseData['data']['categoryUuids']);

        $client->request('GET', '/api/product/PROD-2');
        $responseData = json_decode($client->getResponse()->getContent(), true);
        $this->assertContains('NESTED-CAT-1', $responseData['data']['categoryUuids']);
        $this->assertCount(1, $responseData['data']['categoryUuids']);

        $client->request('GET', '/api/category/NESTED-CAT-1');
        $responseData = json_decode($client->getResponse()->getContent(), true);

        $this->assertContains('PROD-1', $responseData['data']['productUuids']);
        $this->assertContains('PROD-2', $responseData['data']['productUuids']);
    }
}
