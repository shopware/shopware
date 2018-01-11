<?php declare(strict_types=1);

namespace Shopware\Rest\Test\Controller;

use Shopware\Api\Category\Definition\CategoryDefinition;
use Shopware\Api\Product\Definition\ProductDefinition;
use Shopware\Rest\Controller\SyncController;
use Shopware\Rest\Test\ApiTestCase;
use Symfony\Component\HttpFoundation\Response;

class SyncControllerTest extends ApiTestCase
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
                    'id' => 'CREATE-1',
                    'name' => 'CREATE-1',
                ],
            ],
            [
                'action' => SyncController::ACTION_UPSERT,
                'entity' => ProductDefinition::getEntityName(),
                'payload' => [
                    'id' => 'CREATE-2',
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
                    'id' => 'CREATE-1',
                    'active' => true,
                    'name' => 'CREATE-1',
                ],
            ],
            [
                'action' => SyncController::ACTION_UPSERT,
                'entity' => ProductDefinition::getEntityName(),
                'payload' => [
                    'id' => 'CREATE-1',
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
                    'id' => 'CAT-1',
                    'name' => 'CAT-1',
                ],
            ],
            [
                'action' => SyncController::ACTION_UPSERT,
                'entity' => ProductDefinition::getEntityName(),
                'payload' => [
                    'id' => 'PROD-1',
                    'name' => 'PROD-1',
                    'categories' => [
                        ['categoryId' => 'CAT-1'],
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
        $this->assertContains('CAT-1', $responseData['data']['categoryIds']);
        $this->assertCount(1, $responseData['data']['categoryIds'], 'Category Ids should not contain: ' . print_r(array_diff($responseData['data']['categoryIds'], ['CAT-1']), true));
    }

    public function testNestedInsertAndLinkAfter()
    {
        $data = [
            [
                'action' => SyncController::ACTION_UPSERT,
                'entity' => ProductDefinition::getEntityName(),
                'payload' => [
                    'id' => 'PROD-1',
                    'name' => 'PROD-1',
                    'categories' => [
                        [
                            'category' => [
                                'id' => 'NESTED-CAT-1',
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
                    'id' => 'PROD-2',
                    'name' => 'PROD-2',
                    'categories' => [
                        ['categoryId' => 'NESTED-CAT-1'],
                    ],
                ],
            ],
        ];

        $client = $this->getClient();
        $client->request('POST', '/api/sync', $data);

        $client->request('GET', '/api/product/PROD-1');
        $responseData = json_decode($client->getResponse()->getContent(), true);
        $this->assertContains('CAT-1', $responseData['data']['categoryIds']);
        $this->assertContains('NESTED-CAT-1', $responseData['data']['categoryIds']);
        $this->assertCount(2, $responseData['data']['categoryIds']);

        $client->request('GET', '/api/product/PROD-2');
        $responseData = json_decode($client->getResponse()->getContent(), true);
        $this->assertContains('NESTED-CAT-1', $responseData['data']['categoryIds']);
        $this->assertCount(1, $responseData['data']['categoryIds']);

        $client->request('GET', '/api/category/NESTED-CAT-1');
        $responseData = json_decode($client->getResponse()->getContent(), true);

        $this->assertContains('PROD-1', $responseData['data']['productIds']);
        $this->assertContains('PROD-2', $responseData['data']['productIds']);
    }
}
