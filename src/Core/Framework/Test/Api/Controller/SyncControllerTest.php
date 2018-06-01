<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Api\Controller;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Api\Controller\SyncController;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Core\Framework\Test\Api\ApiTestCase;
use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\Response;

class SyncControllerTest extends ApiTestCase
{
    /**
     * @var Connection
     */
    private $connection;

    protected function setUp()
    {
        parent::setUp();

        $this->connection = $this->getContainer()->get(Connection::class);
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
                    'id' => $id1->getHex(),
                    'manufacturer' => ['name' => 'test'],
                    'tax' => ['name' => 'test', 'rate' => 15],
                    'name' => 'CREATE-1',
                    'price' => ['gross' => 50, 'net' => 25],
                ],
            ],
            [
                'action' => SyncController::ACTION_UPSERT,
                'entity' => ProductDefinition::getEntityName(),
                'payload' => [
                    'id' => $id2->getHex(),
                    'manufacturer' => ['name' => 'test'],
                    'name' => 'CREATE-2',
                    'tax' => ['name' => 'test', 'rate' => 15],
                    'price' => ['gross' => 50, 'net' => 25],
                ],
            ],
        ];

        $this->apiClient->request('POST', '/api/sync', [], [], [], json_encode($data));
        $response = $this->apiClient->getResponse();

        self::assertSame(200, $response->getStatusCode(), $response->getContent());

        $this->apiClient->request('GET', '/api/v' . PlatformRequest::API_VERSION . '/product/' . $id1->getHex());
        $this->assertSame(Response::HTTP_OK, $this->apiClient->getResponse()->getStatusCode());

        $this->apiClient->request('GET', '/api/v' . PlatformRequest::API_VERSION . '/product/' . $id2->getHex());
        $this->assertSame(Response::HTTP_OK, $this->apiClient->getResponse()->getStatusCode());

        $this->apiClient->request('DELETE', '/api/v' . PlatformRequest::API_VERSION . '/product/' . $id1->getHex());
        $this->assertSame(Response::HTTP_NO_CONTENT, $this->apiClient->getResponse()->getStatusCode());

        $this->apiClient->request('DELETE', '/api/v' . PlatformRequest::API_VERSION . '/product/' . $id2->getHex());
        $this->assertSame(Response::HTTP_NO_CONTENT, $this->apiClient->getResponse()->getStatusCode());
    }

    public function testInsertAndUpdateSameEntity(): void
    {
        $id = Uuid::uuid4()->getHex();

        $data = [
            [
                'action' => SyncController::ACTION_UPSERT,
                'entity' => ProductDefinition::getEntityName(),
                'payload' => [
                    'id' => $id,
                    'active' => true,
                    'tax' => ['name' => 'test', 'rate' => 15],
                    'name' => 'CREATE-1',
                    'manufacturer' => ['name' => 'test'],
                    'price' => ['gross' => 50, 'net' => 25],
                ],
            ],
            [
                'action' => SyncController::ACTION_UPSERT,
                'entity' => ProductDefinition::getEntityName(),
                'payload' => [
                    'id' => $id,
                    'manufacturer' => ['name' => 'test'],
                    'tax' => ['name' => 'test', 'rate' => 15],
                    'active' => false,
                    'price' => ['gross' => 50, 'net' => 25],
                ],
            ],
        ];

        $this->apiClient->request('POST', '/api/sync', [], [], [], json_encode($data));
        self::assertSame(200, $this->apiClient->getResponse()->getStatusCode(), $this->apiClient->getResponse()->getContent());

        $this->apiClient->request('GET', '/api/v' . PlatformRequest::API_VERSION . '/product/' . $id);
        $this->assertSame(Response::HTTP_OK, $this->apiClient->getResponse()->getStatusCode());

        $responseData = json_decode($this->apiClient->getResponse()->getContent(), true);
        $this->assertEquals(false, $responseData['data']['attributes']['active']);

        $this->apiClient->request('DELETE', '/api/v' . PlatformRequest::API_VERSION . '/product/' . $id);
        $this->assertSame(Response::HTTP_NO_CONTENT, $this->apiClient->getResponse()->getStatusCode());
    }

    public function testInsertAndLinkEntities(): void
    {
        $categoryId = Uuid::uuid4()->getHex();
        $productId = Uuid::uuid4()->getHex();

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
                    'tax' => ['name' => 'test', 'rate' => 15],
                    'name' => 'PROD-1',
                    'price' => ['gross' => 50, 'net' => 25],
                    'manufacturer' => ['name' => 'test'],
                    'categories' => [
                        ['id' => $categoryId],
                    ],
                ],
            ],
        ];

        $this->apiClient->request('POST', '/api/sync', [], [], [], json_encode($data));

        $response = $this->apiClient->getResponse();
        self::assertSame(200, $response->getStatusCode());

        $this->apiClient->request('GET', '/api/v' . PlatformRequest::API_VERSION . '/product/' . $productId);
        $responseData = json_decode($this->apiClient->getResponse()->getContent(), true);

        $this->assertSame(Response::HTTP_OK, $this->apiClient->getResponse()->getStatusCode());
        $categories = array_column($responseData['data']['relationships']['categories']['data'], 'id');

        $this->assertContains($categoryId, $categories);
        $this->assertCount(1, $categories, 'Category Ids should not contain: ' . print_r(array_diff($categories, [$categoryId]), true));

        $this->apiClient->request('DELETE', '/api/v' . PlatformRequest::API_VERSION . '/category/' . $categoryId);
        $this->assertSame(Response::HTTP_NO_CONTENT, $this->apiClient->getResponse()->getStatusCode(), $this->apiClient->getResponse()->getContent());

        $this->apiClient->request('DELETE', '/api/v' . PlatformRequest::API_VERSION . '/product/' . $productId);
        $this->assertSame(Response::HTTP_NO_CONTENT, $this->apiClient->getResponse()->getStatusCode(), $this->apiClient->getResponse()->getContent());
    }

    public function testNestedInsertAndLinkAfter(): void
    {
        $product = Uuid::uuid4()->getHex();
        $product2 = Uuid::uuid4()->getHex();
        $category = Uuid::uuid4()->getHex();

        $data = [
            [
                'action' => SyncController::ACTION_UPSERT,
                'entity' => ProductDefinition::getEntityName(),
                'payload' => [
                    'id' => $product,
                    'name' => 'PROD-1',
                    'manufacturer' => ['name' => 'test'],
                    'price' => ['gross' => 50, 'net' => 25],
                    'tax' => ['name' => 'test', 'rate' => 15],
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
                    'tax' => ['name' => 'test', 'rate' => 15],
                    'price' => ['gross' => 50, 'net' => 25],
                    'manufacturer' => ['name' => 'test'],
                    'categories' => [
                        ['id' => $category],
                    ],
                ],
            ],
        ];

        $this->apiClient->request('POST', '/api/sync', [], [], [], json_encode($data));

        $this->apiClient->request('GET', '/api/v' . PlatformRequest::API_VERSION . '/product/' . $product);
        $responseData = json_decode($this->apiClient->getResponse()->getContent(), true);
        $categories = array_column($responseData['data']['relationships']['categories']['data'], 'id');
        $this->assertContains($category, $categories);
        $this->assertCount(1, $categories);

        $this->apiClient->request('GET', '/api/v' . PlatformRequest::API_VERSION . '/product/' . $product2);
        $responseData = json_decode($this->apiClient->getResponse()->getContent(), true);
        $categories = array_column($responseData['data']['relationships']['categories']['data'], 'id');
        $this->assertContains($category, $categories);
        $this->assertCount(1, $categories);

        $this->apiClient->request('GET', '/api/v' . PlatformRequest::API_VERSION . '/category/' . $category . '/products/');
        $responseData = json_decode($this->apiClient->getResponse()->getContent(), true);
        $products = array_column($responseData['data'], 'id');

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
                    'id' => $product->getHex(),
                    'name' => 'PROD-1',
                    'tax' => ['name' => 'test', 'rate' => 15],
                    'price' => ['gross' => 50, 'net' => 25],
                    'manufacturer' => ['name' => 'test'],
                ],
            ],
            [
                'action' => SyncController::ACTION_UPSERT,
                'entity' => ProductDefinition::getEntityName(),
                'payload' => [
                    'id' => $product2->getHex(),
                    'tax' => ['name' => 'test', 'rate' => 15],
                    'name' => 'PROD-2',
                    'price' => ['gross' => 50, 'net' => 25],
                    'manufacturer' => ['name' => 'test'],
                ],
            ],
        ];

        $this->apiClient->request('POST', '/api/sync', [], [], [], json_encode($data));

        $exists = $this->connection->fetchAll(
            'SELECT * FROM product WHERE id IN(:id)',
            ['id' => [$product->getBytes(), $product2->getBytes()]],
            ['id' => Connection::PARAM_STR_ARRAY]
        );
        $this->assertCount(2, $exists);

        $data = [
            [
                'action' => SyncController::ACTION_DELETE,
                'entity' => ProductDefinition::getEntityName(),
                'payload' => ['id' => $product->getHex()],
            ],
            [
                'action' => SyncController::ACTION_DELETE,
                'entity' => ProductDefinition::getEntityName(),
                'payload' => ['id' => $product2->getHex()],
            ],
        ];

        $this->apiClient->request('POST', '/api/sync', [], [], [], json_encode($data));

        $exists = $this->connection->fetchAll(
            'SELECT * FROM product WHERE id IN (:id)',
            ['id' => [$product->getBytes(), $product2->getBytes()]],
            ['id' => Connection::PARAM_STR_ARRAY]
        );
        $this->assertEmpty($exists);
    }
}
