<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Api\Controller;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Product\DataAbstractionLayer\ProductIndexer;
use Shopware\Core\Content\Product\DataAbstractionLayer\ProductIndexingMessage;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Controller\SyncController;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexerRegistry;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\Response;

/**
 * @group slow
 */
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
                'entity' => $this->getContainer()->get(ProductDefinition::class)->getEntityName(),
                'payload' => [
                    [
                        'id' => $id1,
                        'productNumber' => Uuid::randomHex(),
                        'stock' => 1,
                        'manufacturer' => ['name' => 'test'],
                        'tax' => ['name' => 'test', 'taxRate' => 15],
                        'name' => 'CREATE-1',
                        'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 50, 'net' => 25, 'linked' => false]],
                    ],
                    [
                        'id' => $id2,
                        'productNumber' => Uuid::randomHex(),
                        'stock' => 1,
                        'manufacturer' => ['name' => 'test'],
                        'name' => 'CREATE-2',
                        'tax' => ['name' => 'test', 'taxRate' => 15],
                        'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 50, 'net' => 25, 'linked' => false]],
                    ],
                ],
            ],
        ];

        $this->getBrowser()->request('POST', '/api/_action/sync', [], [], [], json_encode($data));
        $response = $this->getBrowser()->getResponse();

        static::assertSame(200, $response->getStatusCode(), $response->getContent());

        $this->getBrowser()->request('GET', '/api/product/' . $id1);
        static::assertSame(Response::HTTP_OK, $this->getBrowser()->getResponse()->getStatusCode());

        $this->getBrowser()->request('GET', '/api/product/' . $id2);
        static::assertSame(Response::HTTP_OK, $this->getBrowser()->getResponse()->getStatusCode());

        $this->getBrowser()->request('DELETE', '/api/product/' . $id1);
        static::assertSame(Response::HTTP_NO_CONTENT, $this->getBrowser()->getResponse()->getStatusCode());

        $this->getBrowser()->request('DELETE', '/api/product/' . $id2);
        static::assertSame(Response::HTTP_NO_CONTENT, $this->getBrowser()->getResponse()->getStatusCode());
    }

    public function testInsertAndUpdateSameEntity(): void
    {
        $id = Uuid::randomHex();
        $productNumber = Uuid::randomHex();

        $data = [
            [
                'action' => SyncController::ACTION_UPSERT,
                'entity' => $this->getContainer()->get(ProductDefinition::class)->getEntityName(),
                'payload' => [
                    [
                        'id' => $id,
                        'productNumber' => $productNumber,
                        'active' => true,
                        'stock' => 1,
                        'tax' => ['name' => 'test', 'taxRate' => 15],
                        'name' => 'CREATE-1',
                        'manufacturer' => ['name' => 'test'],
                        'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 50, 'net' => 25, 'linked' => false]],
                    ],
                    [
                        'id' => $id,
                        'productNumber' => $productNumber,
                        'manufacturer' => ['name' => 'test'],
                        'stock' => 1,
                        'tax' => ['name' => 'test', 'taxRate' => 15],
                        'active' => false,
                        'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 50, 'net' => 25, 'linked' => false]],
                    ],
                ],
            ],
        ];

        $this->getBrowser()->request('POST', '/api/_action/sync', [], [], [], json_encode($data));
        static::assertSame(200, $this->getBrowser()->getResponse()->getStatusCode(), $this->getBrowser()->getResponse()->getContent());

        $this->getBrowser()->request('GET', '/api/product/' . $id);
        static::assertSame(Response::HTTP_OK, $this->getBrowser()->getResponse()->getStatusCode());

        $responseData = json_decode($this->getBrowser()->getResponse()->getContent(), true);
        static::assertFalse($responseData['data']['attributes']['active']);

        $this->getBrowser()->request('DELETE', '/api/product/' . $id);
        static::assertSame(Response::HTTP_NO_CONTENT, $this->getBrowser()->getResponse()->getStatusCode());
    }

    public function testInsertAndLinkEntities(): void
    {
        $categoryId = Uuid::randomHex();
        $productId = Uuid::randomHex();

        $data = [
            [
                'action' => SyncController::ACTION_UPSERT,
                'entity' => $this->getContainer()->get(CategoryDefinition::class)->getEntityName(),
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
                'entity' => $this->getContainer()->get(ProductDefinition::class)->getEntityName(),
                'payload' => [
                    [
                        'id' => $productId,
                        'productNumber' => Uuid::randomHex(),
                        'stock' => 1,
                        'tax' => ['name' => 'test', 'taxRate' => 15],
                        'name' => 'PROD-1',
                        'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 50, 'net' => 25, 'linked' => false]],
                        'manufacturer' => ['name' => 'test'],
                        'categories' => [
                            ['id' => $categoryId],
                        ],
                    ],
                ],
            ],
        ];

        $this->getBrowser()->request('POST', '/api/_action/sync', [], [], [], json_encode($data));

        $response = $this->getBrowser()->getResponse();
        static::assertSame(200, $response->getStatusCode());

        $this->getBrowser()->request('GET', '/api/product/' . $productId . '/categories');
        $responseData = json_decode($this->getBrowser()->getResponse()->getContent(), true);

        static::assertSame(Response::HTTP_OK, $this->getBrowser()->getResponse()->getStatusCode());
        $categories = array_column($responseData['data'], 'id');

        static::assertContains($categoryId, $categories);
        static::assertCount(1, $categories, 'Category Ids should not contain: ' . print_r(array_diff($categories, [$categoryId]), true));

        $this->getBrowser()->request('DELETE', '/api/category/' . $categoryId);
        static::assertSame(Response::HTTP_NO_CONTENT, $this->getBrowser()->getResponse()->getStatusCode(), $this->getBrowser()->getResponse()->getContent());

        $this->getBrowser()->request('DELETE', '/api/product/' . $productId);
        static::assertSame(Response::HTTP_NO_CONTENT, $this->getBrowser()->getResponse()->getStatusCode(), $this->getBrowser()->getResponse()->getContent());
    }

    public function testNestedInsertAndLinkAfter(): void
    {
        $product = Uuid::randomHex();
        $product2 = Uuid::randomHex();
        $category = Uuid::randomHex();

        $data = [
            [
                'action' => SyncController::ACTION_UPSERT,
                'entity' => $this->getContainer()->get(ProductDefinition::class)->getEntityName(),
                'payload' => [
                    [
                        'id' => $product,
                        'productNumber' => Uuid::randomHex(),
                        'name' => 'PROD-1',
                        'stock' => 1,
                        'manufacturer' => ['name' => 'test'],
                        'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 50, 'net' => 25, 'linked' => false]],
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
                        'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 50, 'net' => 25, 'linked' => false]],
                        'manufacturer' => ['name' => 'test'],
                        'categories' => [
                            ['id' => $category],
                        ],
                    ],
                ],
            ],
        ];

        $this->getBrowser()->request('POST', '/api/_action/sync', [], [], [], json_encode($data));

        $this->getBrowser()->request('GET', '/api/product/' . $product . '/categories');
        $responseData = json_decode($this->getBrowser()->getResponse()->getContent(), true);
        $categories = array_column($responseData['data'], 'id');
        static::assertContains($category, $categories);
        static::assertCount(1, $categories);

        $this->getBrowser()->request('GET', '/api/product/' . $product2 . '/categories');
        $responseData = json_decode($this->getBrowser()->getResponse()->getContent(), true);

        $categories = array_column($responseData['data'], 'id');
        static::assertContains($category, $categories);
        static::assertCount(1, $categories);

        $this->getBrowser()->request('GET', '/api/category/' . $category . '/products/');
        $responseData = json_decode($this->getBrowser()->getResponse()->getContent(), true);
        static::assertSame(Response::HTTP_OK, $this->getBrowser()->getResponse()->getStatusCode());

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
                'entity' => $this->getContainer()->get(ProductDefinition::class)->getEntityName(),
                'payload' => [
                    [
                        'id' => $product,
                        'productNumber' => Uuid::randomHex(),
                        'stock' => 1,
                        'name' => 'PROD-1',
                        'tax' => ['name' => 'test', 'taxRate' => 15],
                        'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 50, 'net' => 25, 'linked' => false]],
                        'manufacturer' => ['name' => 'test'],
                    ],
                    [
                        'id' => $product2,
                        'productNumber' => Uuid::randomHex(),
                        'stock' => 1,
                        'tax' => ['name' => 'test', 'taxRate' => 15],
                        'name' => 'PROD-2',
                        'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 50, 'net' => 25, 'linked' => false]],
                        'manufacturer' => ['name' => 'test'],
                    ],
                ],
            ],
        ];

        $this->getBrowser()->request('POST', '/api/_action/sync', [], [], [], json_encode($data));

        $exists = $this->connection->fetchAll(
            'SELECT * FROM product WHERE id IN(:id)',
            ['id' => [Uuid::fromHexToBytes($product), Uuid::fromHexToBytes($product2)]],
            ['id' => Connection::PARAM_STR_ARRAY]
        );
        static::assertCount(2, $exists);

        $data = [
            [
                'action' => SyncController::ACTION_DELETE,
                'entity' => $this->getContainer()->get(ProductDefinition::class)->getEntityName(),
                'payload' => [
                    ['id' => $product],
                    ['id' => $product2],
                ],
            ],
        ];

        $this->getBrowser()->request('POST', '/api/_action/sync', [], [], [], json_encode($data));

        $exists = $this->connection->fetchAll(
            'SELECT * FROM product WHERE id IN (:id)',
            ['id' => [Uuid::fromHexToBytes($product), Uuid::fromHexToBytes($product2)]],
            ['id' => Connection::PARAM_STR_ARRAY]
        );
        static::assertEmpty($exists);
    }

    public function testItThrows400OnFailOnError(): void
    {
        Feature::skipTestIfActive('FEATURE_NEXT_15815', $this);

        $product = Uuid::randomHex();
        $product2 = Uuid::randomHex();

        $data = [
            [
                'action' => SyncController::ACTION_UPSERT,
                'entity' => $this->getContainer()->get(ProductDefinition::class)->getEntityName(),
                'payload' => [
                    [
                        'id' => $product,
                        'productNumber' => Uuid::randomHex(),
                        'name' => 'PROD-1',
                        'tax' => ['name' => 'test', 'taxRate' => 15],
                        'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 50, 'net' => 25, 'linked' => false]],
                    ],
                    [
                        'id' => $product2,
                        'productNumber' => Uuid::randomHex(),
                        'stock' => 1,
                        'tax' => ['name' => 'test', 'taxRate' => 15],
                        'name' => 'PROD-2',
                        'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 50, 'linked' => false]],
                    ],
                ],
            ],
        ];

        $this->getBrowser()->request('POST', '/api/_action/sync', [], [], ['HTTP_Fail-On-Error' => 'true'], json_encode($data));

        $exists = $this->connection->fetchAll(
            'SELECT * FROM product WHERE id IN(:id)',
            ['id' => [Uuid::fromHexToBytes($product), Uuid::fromHexToBytes($product2)]],
            ['id' => Connection::PARAM_STR_ARRAY]
        );
        static::assertNotFalse($exists);

        $response = $this->getBrowser()->getResponse();
        static::assertEquals(400, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);

        foreach ($content['data'][0]['result'] as $result) {
            static::assertEmpty($result['entities']);
            static::assertCount(1, $result['errors']);
        }
    }

    public function testItReturns200WhenFailOnErrorIsFalse(): void
    {
        Feature::skipTestIfActive('FEATURE_NEXT_15815', $this);

        $product = Uuid::randomHex();

        $data = [
            [
                'action' => SyncController::ACTION_UPSERT,
                'entity' => $this->getContainer()->get(ProductDefinition::class)->getEntityName(),
                'payload' => [
                    [
                        'id' => $product,
                        'productNumber' => Uuid::randomHex(),
                        'name' => 'PROD-1',
                        'tax' => ['name' => 'test', 'taxRate' => 15],
                        'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 50, 'net' => 25, 'linked' => false]],
                    ],
                ],
            ],
        ];

        $this->getBrowser()->request('POST', '/api/_action/sync', [], [], ['HTTP_Fail-On-Error' => 'false'], json_encode($data));

        $exists = $this->connection->fetchAll(
            'SELECT * FROM product WHERE id IN(:id)',
            ['id' => [Uuid::fromHexToBytes($product)]],
            ['id' => Connection::PARAM_STR_ARRAY]
        );
        static::assertNotFalse($exists);

        $response = $this->getBrowser()->getResponse();
        static::assertEquals(200, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);

        foreach ($content['data'][0]['result'] as $result) {
            static::assertEmpty($result['entities']);
            static::assertCount(1, $result['errors']);
        }
    }

    public function testIndexingByQueueHeader(): void
    {
        $product = Uuid::randomHex();

        $data = [
            [
                'action' => SyncController::ACTION_UPSERT,
                'entity' => ProductDefinition::ENTITY_NAME,
                'payload' => [
                    [
                        'id' => $product,
                        'productNumber' => Uuid::randomHex(),
                        'stock' => 1,
                        'name' => 'PROD-1',
                        'tax' => ['name' => 'test', 'taxRate' => 15],
                        'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 50, 'net' => 25, 'linked' => false]],
                    ],
                ],
            ],
        ];

        $this->connection->executeUpdate('DELETE FROM enqueue;');
        $this->connection->executeUpdate('DELETE FROM message_queue_stats;');

        $this->getBrowser()->request('POST', '/api/_action/sync', [], [], ['HTTP_Fail-On-Error' => 'false', 'HTTP_indexing-behavior' => EntityIndexerRegistry::USE_INDEXING_QUEUE], json_encode($data));

        $exists = $this->connection->fetchAll(
            'SELECT * FROM product WHERE id IN(:id)',
            ['id' => [Uuid::fromHexToBytes($product)]],
            ['id' => Connection::PARAM_STR_ARRAY]
        );

        static::assertNotEmpty($exists);

        $messages = $this->connection->fetchAssoc(
            'SELECT * FROM message_queue_stats WHERE name = :name',
            ['name' => ProductIndexingMessage::class]
        );

        static::assertNotEmpty($messages);
        static::assertEquals(1, $messages['size']);
    }

    public function testDirectInexing(): void
    {
        $product = Uuid::randomHex();

        $data = [
            [
                'action' => SyncController::ACTION_UPSERT,
                'entity' => ProductDefinition::ENTITY_NAME,
                'payload' => [
                    [
                        'id' => $product,
                        'productNumber' => Uuid::randomHex(),
                        'stock' => 1,
                        'name' => 'PROD-1',
                        'tax' => ['name' => 'test', 'taxRate' => 15],
                        'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 50, 'net' => 25, 'linked' => false]],
                    ],
                ],
            ],
        ];

        $this->connection->executeUpdate('DELETE FROM enqueue;');
        $this->connection->executeUpdate('DELETE FROM message_queue_stats;');

        $this->getBrowser()->request('POST', '/api/_action/sync', [], [], ['HTTP_Fail-On-Error' => 'false'], json_encode($data));

        $exists = $this->connection->fetchAll(
            'SELECT * FROM product WHERE id IN(:id)',
            ['id' => [Uuid::fromHexToBytes($product)]],
            ['id' => Connection::PARAM_STR_ARRAY]
        );

        static::assertNotEmpty($exists);

        $messages = $this->connection->fetchAssoc(
            'SELECT * FROM message_queue_stats WHERE name = :name',
            ['name' => ProductIndexingMessage::class]
        );
        static::assertEmpty($messages);
    }

    public function testSkipIndexer(): void
    {
        $id1 = Uuid::randomHex();
        $data = [
            [
                'action' => SyncController::ACTION_UPSERT,
                'entity' => ProductDefinition::ENTITY_NAME,
                'payload' => [
                    [
                        'id' => $id1,
                        'productNumber' => Uuid::randomHex(),
                        'stock' => 1,
                        'manufacturer' => ['name' => 'test'],
                        'tax' => ['name' => 'test', 'taxRate' => 15],
                        'name' => 'CREATE-1',
                        'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 50, 'net' => 25, 'linked' => false]],
                    ],
                ],
            ],
        ];

        $headers = [
            'HTTP_' . PlatformRequest::HEADER_INDEXING_SKIP => ProductIndexer::SEARCH_KEYWORD_UPDATER,
        ];
        $this->getBrowser()->request('POST', '/api/_action/sync', [], [], $headers, json_encode($data));

        static::assertSame(200, $this->getBrowser()->getResponse()->getStatusCode());

        $connection = $this->getContainer()->get(Connection::class);

        $count = (int) $connection->fetchOne('SELECT COUNT(*) FROM product_search_keyword WHERE product_id = ?', [Uuid::fromHexToBytes($id1)]);
        static::assertSame(0, $count, 'Search keywords should be empty as we skipped it');
    }
}
