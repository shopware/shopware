<?php declare(strict_types=1);

namespace Shopware\Rest\Test\Controller;

use Doctrine\DBAL\Connection;
use Ramsey\Uuid\Uuid;
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
    }

    public function testMultipleProductInsert(): void
    {
        $id1 = Uuid::uuid4();
        $id2 = Uuid::uuid4();
        $data = [
            [
                'action' => SyncController::ACTION_UPSERT,
                'entity' => ProductDefinition::getEntityName(),
                'payload' => [
                    'id' => $id1->toString(),
                    'manufacturer' => ['name' => 'test'],
                    'taxId' => '49260353-68e3-4d9f-a695-e017d7a231b9',
                    'name' => 'CREATE-1',
                    'price' => 10,
                ],
            ],
            [
                'action' => SyncController::ACTION_UPSERT,
                'entity' => ProductDefinition::getEntityName(),
                'payload' => [
                    'id' => $id2->toString(),
                    'manufacturer' => ['name' => 'test'],
                    'name' => 'CREATE-2',
                    'taxId' => '49260353-68e3-4d9f-a695-e017d7a231b9',
                    'price' => 10,
                ],
            ],
        ];

        $client = $this->getClient();
        $client->request('POST', '/api/sync', [], [], [], json_encode($data));
        $response = $client->getResponse();

        self::assertSame(200, $response->getStatusCode(), $response->getContent());

        $client->request('GET', '/api/product/' . $id1->toString());
        $this->assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        $client->request('GET', '/api/product/' . $id2->toString());
        $this->assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        $client->request('DELETE', '/api/product/' . $id1->toString());
        $this->assertSame(Response::HTTP_NO_CONTENT, $client->getResponse()->getStatusCode());

        $client->request('DELETE', '/api/product/' . $id2->toString());
        $this->assertSame(Response::HTTP_NO_CONTENT, $client->getResponse()->getStatusCode());
    }

    public function testInsertAndUpdateSameEntity(): void
    {
        $id = Uuid::uuid4()->toString();

        $data = [
            [
                'action' => SyncController::ACTION_UPSERT,
                'entity' => ProductDefinition::getEntityName(),
                'payload' => [
                    'id' => $id,
                    'active' => true,
                    'taxId' => '49260353-68e3-4d9f-a695-e017d7a231b9',
                    'name' => 'CREATE-1',
                    'manufacturer' => ['name' => 'test'],
                    'price' => 10,
                ],
            ],
            [
                'action' => SyncController::ACTION_UPSERT,
                'entity' => ProductDefinition::getEntityName(),
                'payload' => [
                    'id' => $id,
                    'manufacturer' => ['name' => 'test'],
                    'taxId' => '49260353-68e3-4d9f-a695-e017d7a231b9',
                    'active' => false,
                    'price' => 10,
                ],
            ],
        ];

        $client = $this->getClient();
        $client->request('POST', '/api/sync', [], [], [], json_encode($data));
        self::assertSame(200, $client->getResponse()->getStatusCode(), $client->getResponse()->getContent());

        $client->request('GET', '/api/product/' . $id);
        $this->assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        $responseData = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals(false, $responseData['data']['attributes']['active']);

        $client->request('DELETE', '/api/product/' . $id);
        $this->assertSame(Response::HTTP_NO_CONTENT, $client->getResponse()->getStatusCode());
    }

    public function testInsertAndLinkEntities(): void
    {
        $categoryId = Uuid::uuid4()->toString();
        $productId = Uuid::uuid4()->toString();

        $data = [
            [
                'action' => SyncController::ACTION_UPSERT,
                'entity' => CategoryDefinition::getEntityName(),
                'payload' => [
                    'id' => $categoryId,
                    'name' => $productId,
                    'manufacturer' => ['name' => 'test'],
                ],
            ],
            [
                'action' => SyncController::ACTION_UPSERT,
                'entity' => ProductDefinition::getEntityName(),
                'payload' => [
                    'id' => $productId,
                    'taxId' => '49260353-68e3-4d9f-a695-e017d7a231b9',
                    'name' => 'PROD-1',
                    'price' => 10,
                    'manufacturer' => ['name' => 'test'],
                    'categories' => [
                        ['id' => $categoryId],
                    ],
                ],
            ],
        ];

        $client = $this->getClient();
        $client->request('POST', '/api/sync', [], [], [], json_encode($data));

        $response = $client->getResponse();
        self::assertSame(200, $response->getStatusCode());

        $client->request('GET', '/api/product/' . $productId);
        $responseData = json_decode($client->getResponse()->getContent(), true);

        $this->assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        $categories = array_column($responseData['data']['relationships']['categories']['data'], 'id');

        $this->assertContains($categoryId, $categories);
        $this->assertCount(1, $categories, 'Category Ids should not contain: ' . print_r(array_diff($categories, [$categoryId]), true));

        $client = $this->getClient();
        $client->request('DELETE', '/api/category/' . $categoryId);
        $this->assertSame(Response::HTTP_NO_CONTENT, $client->getResponse()->getStatusCode(), $client->getResponse()->getContent());

        $client = $this->getClient();
        $client->request('DELETE', '/api/product/' . $productId);
        $this->assertSame(Response::HTTP_NO_CONTENT, $client->getResponse()->getStatusCode(), $client->getResponse()->getContent());
    }

    public function testNestedInsertAndLinkAfter(): void
    {
        $product = Uuid::uuid4()->toString();
        $product2 = Uuid::uuid4()->toString();
        $category = Uuid::uuid4()->toString();
        $data = [
            [
                'action' => SyncController::ACTION_UPSERT,
                'entity' => ProductDefinition::getEntityName(),
                'payload' => [
                    'id' => $product,
                    'name' => 'PROD-1',
                    'manufacturer' => ['name' => 'test'],
                    'price' => 10,
                    'taxId' => '49260353-68e3-4d9f-a695-e017d7a231b9',
                    'categories' => [
                        ['id' => $category, 'name' => 'NESTED-CAT-1'],
                    ],
                ],
            ],
            [
                'action' => SyncController::ACTION_UPSERT,
                'entity' => ProductDefinition::getEntityName(),
                'payload' => [
                    'id' => $product2,
                    'name' => 'PROD-2',
                    'taxId' => '49260353-68e3-4d9f-a695-e017d7a231b9',
                    'price' => 10,
                    'manufacturer' => ['name' => 'test'],
                    'categories' => [
                        ['id' => $category],
                    ],
                ],
            ],
        ];

        $client = $this->getClient();
        $client->request('POST', '/api/sync', [], [], [], json_encode($data));

        $client->request('GET', '/api/product/' . $product);
        $responseData = json_decode($client->getResponse()->getContent(), true);

        $categories = array_column($responseData['data']['relationships']['categories']['data'], 'id');
        $this->assertContains($category, $categories);
        $this->assertCount(1, $categories);

        $client->request('GET', '/api/product/' . $product2);
        $responseData = json_decode($client->getResponse()->getContent(), true);
        $categories = array_column($responseData['data']['relationships']['categories']['data'], 'id');
        $this->assertContains($category, $categories);
        $this->assertCount(1, $categories);

        $client->request('GET', '/api/category/' . $category);
        $responseData = json_decode($client->getResponse()->getContent(), true);
        $products = array_column($responseData['data']['relationships']['products']['data'], 'id');

        $this->assertContains($product, $products);
        $this->assertContains($product2, $products);
    }

    public function testMultiDelete(): void
    {
        $product = Uuid::uuid4();
        $product2 = Uuid::uuid4();

        $data = [
            [
                'action' => SyncController::ACTION_UPSERT,
                'entity' => ProductDefinition::getEntityName(),
                'payload' => [
                    'id' => $product->toString(),
                    'name' => 'PROD-1',
                    'taxId' => '49260353-68e3-4d9f-a695-e017d7a231b9',
                    'price' => 10,
                    'manufacturer' => ['name' => 'test'],
                ],
            ],
            [
                'action' => SyncController::ACTION_UPSERT,
                'entity' => ProductDefinition::getEntityName(),
                'payload' => [
                    'id' => $product2->toString(),
                    'taxId' => '49260353-68e3-4d9f-a695-e017d7a231b9',
                    'name' => 'PROD-2',
                    'price' => 10,
                    'manufacturer' => ['name' => 'test'],
                ],
            ],
        ];

        $client = $this->getClient();
        $client->request('POST', '/api/sync', [], [], [], json_encode($data));

        /** @var Connection $connection */
        $connection = self::$container->get(Connection::class);
        $exists = $connection->fetchAll(
            'SELECT * FROM product WHERE id IN(:id)',
            ['id' => [$product->getBytes(), $product2->getBytes()]],
            ['id' => Connection::PARAM_STR_ARRAY]
        );
        $this->assertCount(2, $exists);

        $data = [
            [
                'action' => SyncController::ACTION_DELETE,
                'entity' => ProductDefinition::getEntityName(),
                'payload' => ['id' => $product->toString()],
            ],
            [
                'action' => SyncController::ACTION_DELETE,
                'entity' => ProductDefinition::getEntityName(),
                'payload' => ['id' => $product2->toString()],
            ],
        ];

        $client = $this->getClient();
        $client->request('POST', '/api/sync', [], [], [], json_encode($data));

        $exists = $connection->fetchAll(
            'SELECT * FROM product WHERE id IN (:id)',
            ['id' => [$product->getBytes(), $product2->getBytes()]],
            ['id' => Connection::PARAM_STR_ARRAY]
        );
        $this->assertEmpty($exists);
    }
}
