<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Api\Controller;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Api\Controller\SyncController;
use Shopware\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\Response;

class SyncControllerTest extends TestCase
{
    use AdminFunctionalTestBehaviour;

    /**
     * @var Connection
     */
    private $connection;

    protected function setUp(): void
    {
        $this->connection = $this->getContainer()->get(Connection::class);
    }

    public function testMultipleProductInsert(): void
    {
        $id1 = Uuid::randomHex();
        $id2 = Uuid::randomHex();
        $data = [
            [
                'action' => SyncController::ACTION_UPSERT,
                'entity' => ProductDefinition::getEntityName(),
                'payload' => [
                    [
                        'id' => $id1,
                        'productNumber' => Uuid::randomHex(),
                        'stock' => 1,
                        'manufacturer' => ['name' => 'test'],
                        'tax' => ['name' => 'test', 'taxRate' => 15],
                        'name' => 'CREATE-1',
                        'price' => ['gross' => 50, 'net' => 25, 'linked' => false],
                    ],
                    [
                        'id' => $id2,
                        'productNumber' => Uuid::randomHex(),
                        'stock' => 1,
                        'manufacturer' => ['name' => 'test'],
                        'name' => 'CREATE-2',
                        'tax' => ['name' => 'test', 'taxRate' => 15],
                        'price' => ['gross' => 50, 'net' => 25, 'linked' => false],
                    ],
                ],
            ],
        ];

        $this->getClient()->request('POST', '/api/v' . PlatformRequest::API_VERSION . '/_action/sync', [], [], [], json_encode($data));
        $response = $this->getClient()->getResponse();

        static::assertSame(200, $response->getStatusCode(), $response->getContent());

        $this->getClient()->request('GET', '/api/v' . PlatformRequest::API_VERSION . '/product/' . $id1);
        static::assertSame(Response::HTTP_OK, $this->getClient()->getResponse()->getStatusCode());

        $this->getClient()->request('GET', '/api/v' . PlatformRequest::API_VERSION . '/product/' . $id2);
        static::assertSame(Response::HTTP_OK, $this->getClient()->getResponse()->getStatusCode());

        $this->getClient()->request('DELETE', '/api/v' . PlatformRequest::API_VERSION . '/product/' . $id1);
        static::assertSame(Response::HTTP_NO_CONTENT, $this->getClient()->getResponse()->getStatusCode());

        $this->getClient()->request('DELETE', '/api/v' . PlatformRequest::API_VERSION . '/product/' . $id2);
        static::assertSame(Response::HTTP_NO_CONTENT, $this->getClient()->getResponse()->getStatusCode());
    }

    public function testInsertAndUpdateSameEntity(): void
    {
        $id = Uuid::randomHex();
        $productNumber = Uuid::randomHex();

        $data = [
            [
                'action' => SyncController::ACTION_UPSERT,
                'entity' => ProductDefinition::getEntityName(),
                'payload' => [
                    [
                        'id' => $id,
                        'productNumber' => $productNumber,
                        'active' => true,
                        'stock' => 1,
                        'tax' => ['name' => 'test', 'taxRate' => 15],
                        'name' => 'CREATE-1',
                        'manufacturer' => ['name' => 'test'],
                        'price' => ['gross' => 50, 'net' => 25, 'linked' => false],
                    ],
                    [
                        'id' => $id,
                        'productNumber' => $productNumber,
                        'manufacturer' => ['name' => 'test'],
                        'stock' => 1,
                        'tax' => ['name' => 'test', 'taxRate' => 15],
                        'active' => false,
                        'price' => ['gross' => 50, 'net' => 25, 'linked' => false],
                    ],
                ],
            ],
        ];

        $this->getClient()->request('POST', '/api/v1/_action/sync', [], [], [], json_encode($data));
        static::assertSame(200, $this->getClient()->getResponse()->getStatusCode(), $this->getClient()->getResponse()->getContent());

        $this->getClient()->request('GET', '/api/v' . PlatformRequest::API_VERSION . '/product/' . $id);
        static::assertSame(Response::HTTP_OK, $this->getClient()->getResponse()->getStatusCode());

        $responseData = json_decode($this->getClient()->getResponse()->getContent(), true);
        static::assertFalse($responseData['data']['attributes']['active']);

        $this->getClient()->request('DELETE', '/api/v' . PlatformRequest::API_VERSION . '/product/' . $id);
        static::assertSame(Response::HTTP_NO_CONTENT, $this->getClient()->getResponse()->getStatusCode());
    }

    public function testInsertAndLinkEntities(): void
    {
        $categoryId = Uuid::randomHex();
        $productId = Uuid::randomHex();

        $data = [
            [
                'action' => SyncController::ACTION_UPSERT,
                'entity' => CategoryDefinition::getEntityName(),
                'payload' => [
                    [
                        'id' => $categoryId,
                        'name' => $productId,
                        'manufacturer' => ['name' => 'test'],
                    ],
                ],
            ],
            [
                'action' => SyncController::ACTION_UPSERT,
                'entity' => ProductDefinition::getEntityName(),
                'payload' => [
                    [
                        'id' => $productId,
                        'productNumber' => Uuid::randomHex(),
                        'stock' => 1,
                        'tax' => ['name' => 'test', 'taxRate' => 15],
                        'name' => 'PROD-1',
                        'price' => ['gross' => 50, 'net' => 25, 'linked' => false],
                        'manufacturer' => ['name' => 'test'],
                        'categories' => [
                            ['id' => $categoryId],
                        ],
                    ],
                ],
            ],
        ];

        $this->getClient()->request('POST', '/api/v1/_action/sync', [], [], [], json_encode($data));

        $response = $this->getClient()->getResponse();
        static::assertSame(200, $response->getStatusCode());

        $this->getClient()->request('GET', '/api/v' . PlatformRequest::API_VERSION . '/product/' . $productId . '/categories');
        $responseData = json_decode($this->getClient()->getResponse()->getContent(), true);

        static::assertSame(Response::HTTP_OK, $this->getClient()->getResponse()->getStatusCode());
        $categories = array_column($responseData['data'], 'id');

        static::assertContains($categoryId, $categories);
        static::assertCount(1, $categories, 'Category Ids should not contain: ' . print_r(array_diff($categories, [$categoryId]), true));

        $this->getClient()->request('DELETE', '/api/v' . PlatformRequest::API_VERSION . '/category/' . $categoryId);
        static::assertSame(Response::HTTP_NO_CONTENT, $this->getClient()->getResponse()->getStatusCode(), $this->getClient()->getResponse()->getContent());

        $this->getClient()->request('DELETE', '/api/v' . PlatformRequest::API_VERSION . '/product/' . $productId);
        static::assertSame(Response::HTTP_NO_CONTENT, $this->getClient()->getResponse()->getStatusCode(), $this->getClient()->getResponse()->getContent());
    }

    public function testNestedInsertAndLinkAfter(): void
    {
        $product = Uuid::randomHex();
        $product2 = Uuid::randomHex();
        $category = Uuid::randomHex();

        $data = [
            [
                'action' => SyncController::ACTION_UPSERT,
                'entity' => ProductDefinition::getEntityName(),
                'payload' => [
                    [
                        'id' => $product,
                        'productNumber' => Uuid::randomHex(),
                        'name' => 'PROD-1',
                        'stock' => 1,
                        'manufacturer' => ['name' => 'test'],
                        'price' => ['gross' => 50, 'net' => 25, 'linked' => false],
                        'tax' => ['name' => 'test', 'taxRate' => 15],
                        'categories' => [
                            ['id' => $category, 'name' => 'NESTED-CAT-1'],
                        ],
                    ],
                    [
                        'id' => $product2,
                        'productNumber' => Uuid::randomHex(),
                        'name' => 'PROD-2',
                        'stock' => 1,
                        'tax' => ['name' => 'test', 'taxRate' => 15],
                        'price' => ['gross' => 50, 'net' => 25, 'linked' => false],
                        'manufacturer' => ['name' => 'test'],
                        'categories' => [
                            ['id' => $category],
                        ],
                    ],
                ],
            ],
        ];

        $this->getClient()->request('POST', '/api/v1/_action/sync', [], [], [], json_encode($data));

        $this->getClient()->request('GET', '/api/v' . PlatformRequest::API_VERSION . '/product/' . $product . '/categories');
        $responseData = json_decode($this->getClient()->getResponse()->getContent(), true);
        $categories = array_column($responseData['data'], 'id');
        static::assertContains($category, $categories);
        static::assertCount(1, $categories);

        $this->getClient()->request('GET', '/api/v' . PlatformRequest::API_VERSION . '/product/' . $product2 . '/categories');
        $responseData = json_decode($this->getClient()->getResponse()->getContent(), true);

        $categories = array_column($responseData['data'], 'id');
        static::assertContains($category, $categories);
        static::assertCount(1, $categories);

        $this->getClient()->request('GET', '/api/v' . PlatformRequest::API_VERSION . '/category/' . $category . '/products/');
        $responseData = json_decode($this->getClient()->getResponse()->getContent(), true);
        static::assertSame(Response::HTTP_OK, $this->getClient()->getResponse()->getStatusCode());

        $products = array_column($responseData['data'], 'id');

        static::assertContains($product, $products);
        static::assertContains($product2, $products);
    }

    public function testMultiDelete(): void
    {
        $product = Uuid::randomHex();
        $product2 = Uuid::randomHex();

        $data = [
            [
                'action' => SyncController::ACTION_UPSERT,
                'entity' => ProductDefinition::getEntityName(),
                'payload' => [
                    [
                        'id' => $product,
                        'productNumber' => Uuid::randomHex(),
                        'stock' => 1,
                        'name' => 'PROD-1',
                        'tax' => ['name' => 'test', 'taxRate' => 15],
                        'price' => ['gross' => 50, 'net' => 25, 'linked' => false],
                        'manufacturer' => ['name' => 'test'],
                    ],
                    [
                        'id' => $product2,
                        'productNumber' => Uuid::randomHex(),
                        'stock' => 1,
                        'tax' => ['name' => 'test', 'taxRate' => 15],
                        'name' => 'PROD-2',
                        'price' => ['gross' => 50, 'net' => 25, 'linked' => false],
                        'manufacturer' => ['name' => 'test'],
                    ],
                ],
            ],
        ];

        $this->getClient()->request('POST', '/api/v1/_action/sync', [], [], [], json_encode($data));

        $exists = $this->connection->fetchAll(
            'SELECT * FROM product WHERE id IN(:id)',
            ['id' => [Uuid::fromHexToBytes($product), Uuid::fromHexToBytes($product2)]],
            ['id' => Connection::PARAM_STR_ARRAY]
        );
        static::assertCount(2, $exists);

        $data = [
            [
                'action' => SyncController::ACTION_DELETE,
                'entity' => ProductDefinition::getEntityName(),
                'payload' => [
                    ['id' => $product],
                    ['id' => $product2],
                ],
            ],
        ];

        $this->getClient()->request('POST', '/api/v1/_action/sync', [], [], [], json_encode($data));

        $exists = $this->connection->fetchAll(
            'SELECT * FROM product WHERE id IN (:id)',
            ['id' => [Uuid::fromHexToBytes($product), Uuid::fromHexToBytes($product2)]],
            ['id' => Connection::PARAM_STR_ARRAY]
        );
        static::assertEmpty($exists);
    }
}
