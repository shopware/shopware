<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Api\Controller;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Product\DataAbstractionLayer\ProductIndexer;
use Shopware\Core\Content\Product\DataAbstractionLayer\ProductIndexingMessage;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Controller\SyncController;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexerRegistry;
use Shopware\Core\Framework\Increment\AbstractIncrementer;
use Shopware\Core\Framework\Increment\IncrementGatewayRegistry;
use Shopware\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\QueueTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 *
 * @group slow
 */
class SyncControllerTest extends TestCase
{
    use AdminFunctionalTestBehaviour;
    use QueueTestBehaviour;

    private Connection $connection;

    private AbstractIncrementer $gateway;

    protected function setUp(): void
    {
        $this->connection = $this->getContainer()->get(Connection::class);
        $this->gateway = $this->getContainer()->get('shopware.increment.gateway.registry')->get(IncrementGatewayRegistry::MESSAGE_QUEUE_POOL);
        $this->gateway->reset('message_queue_stats');
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
                        'manufacturer' => ['name' => 'manufacturer'],
                        'tax' => ['name' => 'tax', 'taxRate' => 15],
                        'name' => 'CREATE-1',
                        'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 50, 'net' => 25, 'linked' => false]],
                    ],
                    [
                        'id' => $id2,
                        'productNumber' => Uuid::randomHex(),
                        'stock' => 1,
                        'manufacturer' => ['name' => 'manufacturer'],
                        'name' => 'CREATE-2',
                        'tax' => ['name' => 'tax', 'taxRate' => 15],
                        'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 50, 'net' => 25, 'linked' => false]],
                    ],
                ],
            ],
        ];

        $this->getBrowser()->request('POST', '/api/_action/sync', [], [], [], json_encode($data, \JSON_THROW_ON_ERROR));
        $response = $this->getBrowser()->getResponse();

        static::assertSame(200, $response->getStatusCode(), (string) $response->getContent());

        $this->getBrowser()->request('GET', '/api/product/' . $id1);
        $response = $this->getBrowser()->getResponse();
        static::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $this->getBrowser()->request('GET', '/api/product/' . $id2);
        $response = $this->getBrowser()->getResponse();
        static::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $this->getBrowser()->request('DELETE', '/api/product/' . $id1);
        $response = $this->getBrowser()->getResponse();
        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());

        $this->getBrowser()->request('DELETE', '/api/product/' . $id2);
        $response = $this->getBrowser()->getResponse();
        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
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

        $this->getBrowser()->request('POST', '/api/_action/sync', [], [], [], json_encode($data, \JSON_THROW_ON_ERROR));
        $response = $this->getBrowser()->getResponse();
        static::assertSame(200, $response->getStatusCode(), (string) $response->getContent());

        $this->getBrowser()->request('GET', '/api/product/' . $id);
        $response = $this->getBrowser()->getResponse();
        static::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $responseData = json_decode((string) $response->getContent(), true, \JSON_THROW_ON_ERROR, \JSON_THROW_ON_ERROR);
        static::assertFalse($responseData['data']['attributes']['active']);

        $this->getBrowser()->request('DELETE', '/api/product/' . $id);
        $response = $this->getBrowser()->getResponse();
        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
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

        $this->getBrowser()->request('POST', '/api/_action/sync', [], [], [], json_encode($data, \JSON_THROW_ON_ERROR));

        $response = $this->getBrowser()->getResponse();
        static::assertSame(200, $response->getStatusCode());

        $this->getBrowser()->request('GET', '/api/product/' . $productId . '/categories');
        $response = $this->getBrowser()->getResponse();
        $responseData = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $categories = array_column($responseData['data'], 'id');

        static::assertContains($categoryId, $categories);
        static::assertCount(1, $categories, 'Category Ids should not contain: ' . print_r(array_diff($categories, [$categoryId]), true));

        $this->getBrowser()->request('DELETE', '/api/category/' . $categoryId);
        $response = $this->getBrowser()->getResponse();
        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode(), (string) $response->getContent());

        $this->getBrowser()->request('DELETE', '/api/product/' . $productId);
        $response = $this->getBrowser()->getResponse();
        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode(), (string) $response->getContent());
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

        $this->getBrowser()->request('POST', '/api/_action/sync', [], [], [], json_encode($data, \JSON_THROW_ON_ERROR));

        $this->getBrowser()->request('GET', '/api/product/' . $product . '/categories');
        $responseData = json_decode((string) $this->getBrowser()->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        $categories = array_column($responseData['data'], 'id');
        static::assertContains($category, $categories);
        static::assertCount(1, $categories);

        $this->getBrowser()->request('GET', '/api/product/' . $product2 . '/categories');
        $responseData = json_decode((string) $this->getBrowser()->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        $categories = array_column($responseData['data'], 'id');
        static::assertContains($category, $categories);
        static::assertCount(1, $categories);

        $this->getBrowser()->request('GET', '/api/category/' . $category . '/products/');
        $responseData = json_decode((string) $this->getBrowser()->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
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

        $this->getBrowser()->request('POST', '/api/_action/sync', [], [], [], json_encode($data, \JSON_THROW_ON_ERROR));

        $exists = $this->connection->fetchAllAssociative(
            'SELECT * FROM product WHERE id IN(:id)',
            ['id' => [Uuid::fromHexToBytes($product), Uuid::fromHexToBytes($product2)]],
            ['id' => ArrayParameterType::STRING]
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

        $this->getBrowser()->request('POST', '/api/_action/sync', [], [], [], json_encode($data, \JSON_THROW_ON_ERROR));

        $exists = $this->connection->fetchAllAssociative(
            'SELECT * FROM product WHERE id IN (:id)',
            ['id' => [Uuid::fromHexToBytes($product), Uuid::fromHexToBytes($product2)]],
            ['id' => ArrayParameterType::STRING]
        );
        static::assertEmpty($exists);
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

        $this->connection->executeStatement('DELETE FROM messenger_messages;');
        $this->connection->executeStatement('DELETE FROM `increment`;');

        $this->getBrowser()->request(
            'POST',
            '/api/_action/sync',
            [],
            [],
            ['HTTP_Fail-On-Error' => 'false', 'HTTP_indexing-behavior' => EntityIndexerRegistry::USE_INDEXING_QUEUE],
            json_encode($data, \JSON_THROW_ON_ERROR)
        );

        $exists = $this->connection->fetchAllAssociative(
            'SELECT * FROM product WHERE id IN(:id)',
            ['id' => [Uuid::fromHexToBytes($product)]],
            ['id' => ArrayParameterType::STRING]
        );

        static::assertNotEmpty($exists);

        $messages = $this->gateway->list('message_queue_stats');

        static::assertNotEmpty($messages);
        static::assertNotEmpty($messages[ProductIndexingMessage::class]);
        static::assertEquals(1, $messages[ProductIndexingMessage::class]['count']);
    }

    public function testDirectIndexing(): void
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

        $this->connection->executeStatement('DELETE FROM messenger_messages;');
        $this->connection->executeStatement('DELETE FROM `increment`;');

        $keys = $this->gateway->list('message_queue_stats');
        static::assertEmpty($keys);

        $this->getBrowser()->request(
            'POST',
            '/api/_action/sync',
            [],
            [],
            ['HTTP_Fail-On-Error' => 'false'],
            json_encode($data, \JSON_THROW_ON_ERROR)
        );

        $exists = $this->connection->fetchAllAssociative(
            'SELECT * FROM product WHERE id IN(:id)',
            ['id' => [Uuid::fromHexToBytes($product)]],
            ['id' => ArrayParameterType::STRING]
        );

        static::assertNotEmpty($exists);

        $keys = $this->gateway->list('message_queue_stats');
        static::assertEmpty($keys);
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
        $this->getBrowser()->request('POST', '/api/_action/sync', [], [], $headers, json_encode($data, \JSON_THROW_ON_ERROR));

        static::assertSame(200, $this->getBrowser()->getResponse()->getStatusCode());

        $connection = $this->getContainer()->get(Connection::class);

        $count = (int) $connection->fetchOne('SELECT COUNT(*) FROM product_search_keyword WHERE product_id = ?', [Uuid::fromHexToBytes($id1)]);
        static::assertSame(0, $count, 'Search keywords should be empty as we skipped it');
    }

    public static function invalidOperationProvider(): \Generator
    {
        yield 'Invalid entity argument' => [
            'invalid-entity',
            '',
            'upsert',
            [
                ['id' => 'id1', 'name' => 'first manufacturer'],
                ['id' => 'id2', 'name' => 'second manufacturer'],
            ],
            'entity',
        ];

        yield 'Missing action argument' => [
            'missing-action',
            ProductDefinition::ENTITY_NAME,
            '',
            [
                ['id' => 'id1', 'name' => 'first manufacturer'],
                ['id' => 'id2', 'name' => 'second manufacturer'],
            ],
            'action',
        ];

        yield 'Invalid action argument' => [
            'missing-action',
            ProductDefinition::ENTITY_NAME,
            'invalid-action',
            [
                ['id' => 'id1', 'name' => 'first manufacturer'],
                ['id' => 'id2', 'name' => 'second manufacturer'],
            ],
            'action',
        ];

        yield 'Missing payload argument' => [
            'missing-action',
            ProductDefinition::ENTITY_NAME,
            'upsert',
            [],
            'payload',
        ];
    }

    /**
     * @dataProvider invalidOperationProvider
     *
     * @param array<mixed> $payload
     */
    public function testItThrows400WithInvalidSyncOperation(string $key, string $entity, string $action, array $payload, string $actor): void
    {
        $data = [
            [
                'action' => $action,
                'entity' => $entity,
                'payload' => $payload,
            ],
        ];

        $this->getBrowser()->request('POST', '/api/_action/sync', [], [], ['HTTP_Fail-On-Error' => 'true'], json_encode($data, \JSON_THROW_ON_ERROR));

        $response = $this->getBrowser()->getResponse();
        static::assertEquals(400, $response->getStatusCode());

        $content = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertEquals('FRAMEWORK__INVALID_SYNC_OPERATION', $content['errors'][0]['code']);
        static::assertStringContainsString($actor, $content['errors'][0]['detail']);
    }
}
