<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Elasticsearch\Admin;

use Doctrine\DBAL\Connection;
use OpenSearch\Client;
use OpenSearch\Exception\RuntimeException;
use OpenSearch\Namespaces\IndicesNamespace;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IterableQuery;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\Event\NestedEventCollection;
use Shopware\Core\Framework\Event\ProgressAdvancedEvent;
use Shopware\Core\Framework\Event\ProgressFinishedEvent;
use Shopware\Core\Framework\Event\ProgressStartedEvent;
use Shopware\Core\Framework\Feature;
use Shopware\Elasticsearch\Admin\AdminElasticsearchHelper;
use Shopware\Elasticsearch\Admin\AdminIndexingBehavior;
use Shopware\Elasticsearch\Admin\AdminSearchIndexingMessage;
use Shopware\Elasticsearch\Admin\AdminSearchRegistry;
use Shopware\Elasticsearch\Admin\Indexer\AbstractAdminIndexer;
use Shopware\Elasticsearch\ElasticsearchException;
use Shopware\Elasticsearch\Framework\AbstractElasticsearchDefinition;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @internal
 */
#[CoversClass(AdminSearchRegistry::class)]
class AdminSearchRegistryTest extends TestCase
{
    private MockObject&AbstractAdminIndexer $indexer;

    protected function setUp(): void
    {
        $this->indexer = $this->getMockBuilder(AbstractAdminIndexer::class)->getMock();
    }

    public function testGetSubscribedEvents(): void
    {
        $events = AdminSearchRegistry::getSubscribedEvents();

        static::assertArrayHasKey(EntityWrittenContainerEvent::class, $events);
    }

    public function testGetIndexers(): void
    {
        $searchHelper = new AdminElasticsearchHelper(true, false, 'sw-admin', 'test', true, new NullLogger());
        $registry = new AdminSearchRegistry(
            ['promotion' => $this->indexer],
            $this->createMock(Connection::class),
            $this->createMock(MessageBusInterface::class),
            $this->createMock(EventDispatcherInterface::class),
            $this->createMock(Client::class),
            $searchHelper,
            $this->createMock(LoggerInterface::class),
            [],
            [],
            'test'
        );
        $indexers = $registry->getIndexers();

        static::assertSame(['promotion' => $this->indexer], $indexers);
    }

    public function testUpdateMapping(): void
    {
        $searchHelper = new AdminElasticsearchHelper(true, false, 'sw-admin', 'test', true, new NullLogger());
        $client = $this->createMock(Client::class);

        $indices = $this->createMock(IndicesNamespace::class);
        $indices->expects($this->once())
            ->method('putMapping')
            ->with([
                'index' => 'sw-admin-',
                'body' => [],
            ]);

        $client->method('indices')->willReturn($indices);

        $registry = new AdminSearchRegistry(
            ['promotion' => $this->indexer],
            $this->createMock(Connection::class),
            $this->createMock(MessageBusInterface::class),
            $this->createMock(EventDispatcherInterface::class),
            $client,
            $searchHelper,
            $this->createMock(LoggerInterface::class),
            [],
            [],
            'test'
        );

        $properties = [
            'id' => AbstractElasticsearchDefinition::KEYWORD_FIELD,
            'textBoosted' => AbstractAdminIndexer::SEARCH_FIELD,
            'text' => AbstractAdminIndexer::SEARCH_FIELD,
            'entityName' => AbstractElasticsearchDefinition::KEYWORD_FIELD,
            'parameters' => AbstractElasticsearchDefinition::KEYWORD_FIELD,
        ];

        if (Feature::isActive('ENABLE_OPENSEARCH_FOR_ADMIN_API')) {
            $properties['textBoosted']['fields']['ngram']['search_analyzer'] = 'sw_whitespace_analyzer';
            $properties['text']['fields']['ngram']['search_analyzer'] = 'sw_whitespace_analyzer';
        }

        $this->indexer->expects($this->once())
            ->method('mapping')
            ->with([
                'properties' => $properties,
            ]);
        $registry->updateMappings();
    }

    public function testGetIndexerWithInvalidName(): void
    {
        $searchHelper = new AdminElasticsearchHelper(true, false, 'sw-admin', 'test', true, new NullLogger());
        $registry = new AdminSearchRegistry(
            ['promotion' => $this->indexer],
            $this->createMock(Connection::class),
            $this->createMock(MessageBusInterface::class),
            $this->createMock(EventDispatcherInterface::class),
            $this->createMock(Client::class),
            $searchHelper,
            $this->createMock(LoggerInterface::class),
            [],
            [],
            'test'
        );
        $this->expectException(ElasticsearchException::class);
        $registry->getIndexer('test');
    }

    public function testGetIndexer(): void
    {
        $searchHelper = new AdminElasticsearchHelper(true, false, 'sw-admin', 'test', true, new NullLogger());
        $registry = new AdminSearchRegistry(
            ['promotion' => $this->indexer],
            $this->createMock(Connection::class),
            $this->createMock(MessageBusInterface::class),
            $this->createMock(EventDispatcherInterface::class),
            $this->createMock(Client::class),
            $searchHelper,
            $this->createMock(LoggerInterface::class),
            [],
            [],
            'test'
        );
        $indexer = $registry->getIndexer('promotion');

        static::assertSame($this->indexer, $indexer);
    }

    public function testIterateWithExistedAliasWillBeSwap(): void
    {
        $this->indexer->method('getName')->willReturn('promotion-listing');

        $client = $this->createMock(Client::class);
        $indices = $this->createMock(IndicesNamespace::class);
        $indices->method('existsAlias')->willReturn(true);
        $indices
            ->method('getAlias')
            ->willReturn([
                'sw-admin-promotion-listing_12345' => [
                    'aliases' => [
                        'sw-admin-promotion-listing' => [],
                    ],
                ],
            ]);
        $indices
            ->expects($this->once())
            ->method('delete')
            ->with(['index' => 'sw-admin-promotion-listing_12345']);

        $client->method('indices')->willReturn($indices);

        $searchHelper = new AdminElasticsearchHelper(true, false, 'sw-admin', 'test', true, new NullLogger());
        $registry = new AdminSearchRegistry(
            ['promotion' => $this->indexer],
            $this->createMock(Connection::class),
            $this->createMock(MessageBusInterface::class),
            $this->createMock(EventDispatcherInterface::class),
            $client,
            $searchHelper,
            $this->createMock(LoggerInterface::class),
            [],
            [],
            'test'
        );

        $registry->iterate(new AdminIndexingBehavior(false));
    }

    /**
     * @param array{index: array{number_of_shards: int|null, number_of_replicas: int|null, test?: int}} $constructorConfig
     */
    #[DataProvider('providerCreateIndices')]
    public function testIterate(array $constructorConfig): void
    {
        $this->indexer->method('getName')->willReturn('promotion-listing');

        $client = $this->createMock(Client::class);
        $indices = $this->createMock(IndicesNamespace::class);
        $indices
            ->expects($this->exactly(2))
            ->method('existsAlias')
            ->with(['name' => 'sw-admin-promotion-listing']);

        $client->method('indices')->willReturn($indices);

        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAllKeyValue')->willReturn(['sw-admin-promotion-listing' => 'sw-admin-promotion-listing_12345']);

        $searchHelper = new AdminElasticsearchHelper(true, false, 'sw-admin', 'test', true, new NullLogger());
        $registry = new AdminSearchRegistry(
            ['promotion' => $this->indexer],
            $connection,
            $this->createMock(MessageBusInterface::class),
            $this->createMock(EventDispatcherInterface::class),
            $client,
            $searchHelper,
            $this->createMock(LoggerInterface::class),
            ['settings' => $constructorConfig],
            [],
            'test'
        );

        $registry->iterate(new AdminIndexingBehavior(true));
    }

    public function testIterateFiresEvents(): void
    {
        $this->indexer->method('getName')->willReturn('promotion-listing');
        $this->indexer->method('getEntity')->willReturn('promotion');

        $query = $this->createMock(IterableQuery::class);
        $firstRun = true;

        $query->expects($this->exactly(2))->method('fetch')->willReturnCallback(static function () use (&$firstRun) {
            if ($firstRun) {
                $firstRun = false;

                return ['1', '2'];
            }

            return [];
        });
        $query->method('fetchCount')->willReturn(2);

        $this->indexer->method('getIterator')->willReturn($query);

        $client = $this->createMock(Client::class);
        $indices = $this->createMock(IndicesNamespace::class);
        $indices
            ->expects($this->exactly(2))
            ->method('existsAlias')
            ->with(['name' => 'sw-admin-promotion-listing']);

        $client->method('indices')->willReturn($indices);

        $eventDispatcher = new EventDispatcher();
        $queue = $this->createMock(MessageBusInterface::class);
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAllKeyValue')->willReturn(['sw-admin-promotion-listing' => 'sw-admin-promotion-listing_12345']);

        $searchHelper = new AdminElasticsearchHelper(true, false, 'sw-admin', 'test', true, new NullLogger());
        $index = new AdminSearchRegistry(
            ['promotion' => $this->indexer],
            $connection,
            $queue,
            $eventDispatcher,
            $client,
            $searchHelper,
            $this->createMock(LoggerInterface::class),
            [],
            [],
            'test'
        );

        $calledStartEvent = false;
        $eventDispatcher->addListener(
            ProgressStartedEvent::class,
            static function (ProgressStartedEvent $event) use (&$calledStartEvent): void {
                $calledStartEvent = true;
                static::assertSame('promotion-listing', $event->getMessage());
                static::assertSame(2, $event->getTotal());
            }
        );

        $calledAdvancedEvent = false;
        $eventDispatcher->addListener(
            ProgressAdvancedEvent::class,
            static function (ProgressAdvancedEvent $event) use (&$calledAdvancedEvent): void {
                $calledAdvancedEvent = true;

                static::assertSame(2, $event->getStep());
            }
        );

        $calledFinishEvent = false;
        $eventDispatcher->addListener(
            ProgressFinishedEvent::class,
            static function (ProgressFinishedEvent $event) use (&$calledFinishEvent): void {
                $calledFinishEvent = true;

                static::assertSame('promotion-listing', $event->getMessage());
            }
        );

        $index->iterate(new AdminIndexingBehavior(true));

        static::assertTrue($calledStartEvent, 'Event ProgressStartedEvent was not dispatched');
        static::assertTrue($calledAdvancedEvent, 'Event ProgressAdvancedEvent was not dispatched');
        static::assertTrue($calledFinishEvent, 'Event ProgressFinishedEvent was not dispatched');
    }

    #[DataProvider('refreshIndicesProvider')]
    public function testRefresh(bool $refreshIndices): void
    {
        $this->indexer->method('getName')->willReturn('promotion-listing');
        $this->indexer->method('getEntity')->willReturn('promotion');
        $this->indexer->method('fetch')->willReturn([
            'c1a28776116d4431a2208eb2960ec340' => [
                'id' => 'c1a28776116d4431a2208eb2960ec340',
                'text' => 'c1a28776116d4431a2208eb2960ec340 elasticsearch',
            ],
        ]);
        $this->indexer->method('getUpdatedIds')->willReturn(['c1a28776116d4431a2208eb2960ec340']);

        $client = $this->createMock(Client::class);

        if ($refreshIndices) {
            $indices = $this->createMock(IndicesNamespace::class);
            $indices
                ->expects($this->exactly(2))
                ->method('existsAlias')
                ->with(['name' => 'sw-admin-promotion-listing']);

            $client->method('indices')->willReturn($indices);
        }

        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAllKeyValue')->willReturn(['sw-admin-promotion-listing' => 'sw-admin-promotion-listing_12345']);

        $searchHelper = new AdminElasticsearchHelper(true, $refreshIndices, 'sw-admin', 'test', true, new NullLogger());
        $queue = $this->createMock(MessageBusInterface::class);

        $client
            ->expects($this->once())
            ->method('bulk')
            ->with([
                'index' => 'sw-admin-promotion-listing_12345',
                'body' => [
                    ['index' => ['_id' => 'c1a28776116d4431a2208eb2960ec340']],
                    [
                        'entityName' => 'promotion',
                        'parameters' => [],
                        'text' => 'c1a28776116d4431a2208eb2960ec340 elasticsearch',
                        'textBoosted' => '',
                        'id' => 'c1a28776116d4431a2208eb2960ec340',
                    ],
                ],
            ]);

        $index = new AdminSearchRegistry(
            ['promotion' => $this->indexer],
            $connection,
            $queue,
            $this->createMock(EventDispatcherInterface::class),
            $client,
            $searchHelper,
            $this->createMock(LoggerInterface::class),
            [],
            [],
            'test'
        );

        $index->refresh(new EntityWrittenContainerEvent(Context::createDefaultContext(), new NestedEventCollection([
            new EntityWrittenEvent('promotion', [
                new EntityWriteResult(
                    'c1a28776116d4431a2208eb2960ec340',
                    [],
                    'promotion',
                    EntityWriteResult::OPERATION_INSERT
                ),
            ], Context::createDefaultContext()),
        ]), []));
    }

    public function testInvokeDeletesWhenToRemoveIdsProvided(): void
    {
        $this->indexer->method('getName')->willReturn('promotion-listing');
        $this->indexer->method('getEntity')->willReturn('promotion');
        $this->indexer->method('fetch')->willReturn([]); // simulate not found -> should delete

        $client = $this->createMock(Client::class);
        $client
            ->expects($this->once())
            ->method('bulk')
            ->with([
                'index' => 'sw-admin-promotion-listing_12345',
                'body' => [
                    ['delete' => ['_id' => 'deadbeefdeadbeefdeadbeefdeadbeef']],
                ],
            ]);

        $indices = ['sw-admin-promotion-listing' => 'sw-admin-promotion-listing_12345'];

        $searchHelper = new AdminElasticsearchHelper(true, false, 'sw-admin', 'test', true, new NullLogger());
        $index = new AdminSearchRegistry(
            ['promotion' => $this->indexer],
            $this->createMock(Connection::class),
            $this->createMock(MessageBusInterface::class),
            $this->createMock(EventDispatcherInterface::class),
            $client,
            $searchHelper,
            $this->createMock(LoggerInterface::class),
            [],
            [],
            'test'
        );

        $index->__invoke(new AdminSearchIndexingMessage(
            'promotion',
            'promotion',
            $indices,
            [],
            ['deadbeefdeadbeefdeadbeefdeadbeef']
        ));
    }

    public function testRefreshLogsAndDoesNotIndexIfExceptionIsThrownDuringRefreshIndices(): void
    {
        $this->indexer->method('getName')->willReturn('promotion-listing');
        $this->indexer->method('getEntity')->willReturn('promotion');
        $this->indexer->expects($this->never())->method('fetch');

        $client = $this->createMock(Client::class);
        $client->expects($this->never())->method('bulk');

        $client->method('indices')->willThrowException(new RuntimeException('no nodes'));

        $connection = $this->createMock(Connection::class);

        $searchHelper = new AdminElasticsearchHelper(true, true, 'sw-admin', 'test', true, new NullLogger());
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('error')
            ->with('Could not refresh indices. Run "bin/console es:admin:mapping:update" & "bin/console es:admin:index" to update indices and reindex. Error: no nodes');

        $index = new AdminSearchRegistry(
            ['promotion' => $this->indexer],
            $connection,
            $this->createMock(MessageBusInterface::class),
            $this->createMock(EventDispatcherInterface::class),
            $client,
            $searchHelper,
            $logger,
            [],
            [],
            'test'
        );

        $index->refresh(new EntityWrittenContainerEvent(Context::createDefaultContext(), new NestedEventCollection([
            new EntityWrittenEvent('promotion', [
                new EntityWriteResult(
                    'c1a28776116d4431a2208eb2960ec340',
                    [],
                    'promotion',
                    EntityWriteResult::OPERATION_INSERT
                ),
            ], Context::createDefaultContext()),
        ]), []));
    }

    public function testRefreshIndicesNoEmptyDbCall(): void
    {
        $client = $this->createMock(Client::class);
        $indices = $this->createMock(IndicesNamespace::class);
        $indices->expects($this->never())->method('existsAlias');

        $client->method('indices')->willReturn($indices);

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('executeStatement');

        $searchHelper = new AdminElasticsearchHelper(true, true, 'sw-admin', 'test', true, new NullLogger());
        $index = new AdminSearchRegistry(
            [],
            $connection,
            $this->createMock(MessageBusInterface::class),
            $this->createMock(EventDispatcherInterface::class),
            $client,
            $searchHelper,
            $this->createMock(LoggerInterface::class),
            [],
            [],
            'test'
        );

        $index->refresh(new EntityWrittenContainerEvent(Context::createDefaultContext(), new NestedEventCollection([
            new EntityWrittenEvent('promotion', [
                new EntityWriteResult(
                    'c1a28776116d4431a2208eb2960ec340',
                    [],
                    'promotion',
                    EntityWriteResult::OPERATION_INSERT
                ),
            ], Context::createDefaultContext()),
        ]), []));
    }

    public function testHandle(): void
    {
        $this->indexer->method('getName')->willReturn('promotion-listing');
        $this->indexer->method('getEntity')->willReturn('promotion');
        $this->indexer->method('fetch')->willReturn([
            'c1a28776116d4431a2208eb2960ec340' => [
                'id' => 'c1a28776116d4431a2208eb2960ec340',
                'text' => 'c1a28776116d4431a2208eb2960ec340 elasticsearch',
            ],
        ]);

        $client = $this->createMock(Client::class);
        $client
            ->expects($this->once())
            ->method('bulk')
            ->with([
                'index' => 'sw-admin-promotion-listing_12345',
                'body' => [
                    [
                        'index' => [
                            '_id' => 'c1a28776116d4431a2208eb2960ec340',
                        ],
                    ],
                    [
                        'entityName' => 'promotion',
                        'parameters' => [],
                        'text' => 'c1a28776116d4431a2208eb2960ec340 elasticsearch',
                        'textBoosted' => '',
                        'id' => 'c1a28776116d4431a2208eb2960ec340',
                    ],
                ],
            ]);

        $indices = ['sw-admin-promotion-listing' => 'sw-admin-promotion-listing_12345'];

        $searchHelper = new AdminElasticsearchHelper(true, false, 'sw-admin', 'test', true, new NullLogger());
        $index = new AdminSearchRegistry(
            ['promotion' => $this->indexer],
            $this->createMock(Connection::class),
            $this->createMock(MessageBusInterface::class),
            $this->createMock(EventDispatcherInterface::class),
            $client,
            $searchHelper,
            $this->createMock(LoggerInterface::class),
            [],
            [],
            'test'
        );

        $index->__invoke(new AdminSearchIndexingMessage(
            'promotion',
            'promotion',
            $indices,
            ['c1a28776116d4431a2208eb2960ec340']
        ));
    }

    public function testHandleThrowErrors(): void
    {
        $this->indexer->method('getName')->willReturn('promotion-listing');
        $this->indexer->method('getEntity')->willReturn('promotion');
        $this->indexer->method('fetch')->willReturn([
            'c1a28776116d4431a2208eb2960ec340' => [
                'id' => 'c1a28776116d4431a2208eb2960ec340',
                'text' => 'c1a28776116d4431a2208eb2960ec340 elasticsearch',
            ],
        ]);

        $client = $this->createMock(Client::class);
        $result = [
            'took' => 100,
            'errors' => true,
            'items' => [
                [
                    'delete' => [
                        '_index' => 'index1',
                        '_id' => '5',
                        'status' => 404,
                        'error' => [
                            'type' => 'document_missing_exception',
                            'reason' => '[5]: document missing',
                            'index_uuid' => 'aAsFqTI0Tc2W0LCWgPNrOA',
                            'shard' => '0',
                            'index' => 'index1',
                        ],
                    ],
                ],
            ],
        ];
        $client->method('bulk')->willReturn($result);

        $indices = ['sw-admin-promotion-listing' => 'sw-admin-promotion-listing_12345'];

        $searchHelper = new AdminElasticsearchHelper(true, false, 'sw-admin', 'test', true, new NullLogger());
        $index = new AdminSearchRegistry(
            ['promotion' => $this->indexer],
            $this->createMock(Connection::class),
            $this->createMock(MessageBusInterface::class),
            $this->createMock(EventDispatcherInterface::class),
            $client,
            $searchHelper,
            $this->createMock(LoggerInterface::class),
            [],
            [],
            'test'
        );

        $this->expectException(ElasticsearchException::class);
        $index->__invoke(new AdminSearchIndexingMessage(
            'promotion',
            'promotion',
            $indices,
            ['c1a28776116d4431a2208eb2960ec340']
        ));
    }

    /**
     * @return \Generator<array<array{index: array{number_of_shards: int|null, number_of_replicas: int|null, test?: int}}>>
     */
    public static function providerCreateIndices(): \Generator
    {
        yield 'with given number of shards' => [
            [
                'index' => [
                    'number_of_shards' => 1,
                    'number_of_replicas' => 5,
                ],
            ],
        ];

        yield 'with null of shards' => [
            [
                'index' => [
                    'number_of_shards' => null,
                    'number_of_replicas' => null,
                ],
            ],
        ];

        yield 'with null of shards with additional field' => [
            [
                'index' => [
                    'number_of_shards' => null,
                    'number_of_replicas' => null,
                    'test' => 1,
                ],
            ],
        ];
    }

    /**
     * @return iterable<array<bool>>
     */
    public static function refreshIndicesProvider(): iterable
    {
        return [
            [true],
            [false],
        ];
    }
}
