<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Elasticsearch\Admin;

use Doctrine\DBAL\Connection;
use OpenSearch\Client;
use OpenSearch\Exception\RuntimeException;
use OpenSearch\Namespaces\IndicesNamespace;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Shopware\Core\Framework\Api\Context\SalesChannelApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IterableQuery;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\Event\NestedEventCollection;
use Shopware\Core\Framework\Event\ProgressAdvancedEvent;
use Shopware\Core\Framework\Event\ProgressFinishedEvent;
use Shopware\Core\Framework\Event\ProgressStartedEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\TestDefaults;
use Shopware\Elasticsearch\Admin\AdminElasticsearchHelper;
use Shopware\Elasticsearch\Admin\AdminIndexingBehavior;
use Shopware\Elasticsearch\Admin\AdminSearchIndexingMessage;
use Shopware\Elasticsearch\Admin\AdminSearchRegistry;
use Shopware\Elasticsearch\Admin\Indexer\AbstractAdminIndexer;
use Shopware\Elasticsearch\ElasticsearchException;
use Shopware\Elasticsearch\Framework\AbstractElasticsearchDefinition;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\DependencyInjection\Argument\RewindableGenerator;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(AdminSearchRegistry::class)]
class AdminSearchRegistryTest extends TestCase
{
    private AbstractAdminIndexer&Stub $indexer;

    protected function setUp(): void
    {
        $this->indexer = static::createStub(AbstractAdminIndexer::class);
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
            static::createStub(Connection::class),
            static::createStub(MessageBusInterface::class),
            static::createStub(EventDispatcherInterface::class),
            static::createStub(Client::class),
            $searchHelper,
            static::createStub(LoggerInterface::class),
            [],
            [],
            'test',
            new NativeClock()
        );
        $indexers = $registry->getIndexers();

        static::assertSame(['promotion' => $this->indexer], $indexers);
    }

    public function testIndexerLookupIsResolvedOnceFromTheTaggedIterator(): void
    {
        $consumed = 0;
        $indexer = $this->indexer;

        $registry = new AdminSearchRegistry(
            new RewindableGenerator(static function () use (&$consumed, $indexer): \Generator {
                ++$consumed;

                yield 'promotion' => $indexer;
            }, 1),
            static::createStub(Connection::class),
            static::createStub(MessageBusInterface::class),
            static::createStub(EventDispatcherInterface::class),
            static::createStub(Client::class),
            new AdminElasticsearchHelper(true, false, 'sw-admin', 'test', true, new NullLogger()),
            static::createStub(LoggerInterface::class),
            [],
            [],
            'test',
            new NativeClock()
        );

        static::assertTrue($registry->hasIndexer('promotion'));
        static::assertSame($this->indexer, $registry->getIndexer('promotion'));
        static::assertTrue($registry->hasIndexer('promotion'));

        static::assertSame(1, $consumed);
    }

    public function testUpdateMapping(): void
    {
        $searchHelper = new AdminElasticsearchHelper(true, false, 'sw-admin', 'test', true, new NullLogger());
        $client = static::createStub(Client::class);

        $indices = $this->createMock(IndicesNamespace::class);
        $indices->expects($this->once())
            ->method('putMapping')
            ->with([
                'index' => 'sw-admin-',
                'body' => [],
            ]);

        $client->method('indices')->willReturn($indices);

        $indexer = $this->createMock(AbstractAdminIndexer::class);

        $registry = new AdminSearchRegistry(
            ['promotion' => $indexer],
            static::createStub(Connection::class),
            static::createStub(MessageBusInterface::class),
            static::createStub(EventDispatcherInterface::class),
            $client,
            $searchHelper,
            static::createStub(LoggerInterface::class),
            [],
            [],
            'test',
            new NativeClock()
        );

        $properties = [
            'id' => AbstractElasticsearchDefinition::KEYWORD_FIELD,
            'textBoosted' => AbstractAdminIndexer::TEXT_FIELD,
            'text' => AbstractAdminIndexer::TEXT_FIELD,
            'completion' => AbstractAdminIndexer::COMPLETION_FIELD,
            'entityName' => AbstractElasticsearchDefinition::KEYWORD_FIELD,
            'parameters' => AbstractElasticsearchDefinition::KEYWORD_FIELD,
        ];

        $indexer->expects($this->once())
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
            static::createStub(Connection::class),
            static::createStub(MessageBusInterface::class),
            static::createStub(EventDispatcherInterface::class),
            static::createStub(Client::class),
            $searchHelper,
            static::createStub(LoggerInterface::class),
            [],
            [],
            'test',
            new NativeClock()
        );
        $this->expectException(ElasticsearchException::class);
        $registry->getIndexer('test');
    }

    public function testGetIndexer(): void
    {
        $searchHelper = new AdminElasticsearchHelper(true, false, 'sw-admin', 'test', true, new NullLogger());
        $registry = new AdminSearchRegistry(
            ['promotion' => $this->indexer],
            static::createStub(Connection::class),
            static::createStub(MessageBusInterface::class),
            static::createStub(EventDispatcherInterface::class),
            static::createStub(Client::class),
            $searchHelper,
            static::createStub(LoggerInterface::class),
            [],
            [],
            'test',
            new NativeClock()
        );
        $indexer = $registry->getIndexer('promotion');

        static::assertSame($this->indexer, $indexer);
    }

    public function testIterateSwapsAliasOfAFinishedIndexingRun(): void
    {
        $this->indexer->method('getName')->willReturn('promotion-listing');
        $this->indexer->method('getEntity')->willReturn('promotion');

        $client = static::createStub(Client::class);
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

        // the messages were handled inline, so the run has no remaining documents and its index is promoted
        $connection = static::createStub(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([
            ['alias' => 'sw-admin-promotion-listing', 'index' => 'sw-admin-promotion-listing_67890'],
        ]);

        $registry = $this->createRegistry(['promotion' => $this->indexer], $client, $connection);

        $registry->iterate(new AdminIndexingBehavior(true));
    }

    public function testIterateLeavesTheAliasAloneWhileDocumentsAreStillPending(): void
    {
        $this->indexer->method('getName')->willReturn('promotion-listing');
        $this->indexer->method('getEntity')->willReturn('promotion');

        $client = static::createStub(Client::class);
        $indices = $this->createMock(IndicesNamespace::class);
        $indices->method('existsAlias')->willReturn(true);
        $indices->expects($this->never())->method('delete');
        $indices->expects($this->never())->method('putAlias');

        $client->method('indices')->willReturn($indices);

        // no row reports zero remaining documents, so AdminCreateAliasTask has to do the swap later
        $connection = static::createStub(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([]);

        $registry = $this->createRegistry(['promotion' => $this->indexer], $client, $connection);

        $registry->iterate(new AdminIndexingBehavior(false));
    }

    public function testSwapFinishedAliasesPromotesTheNewestOfSeveralFinishedIndices(): void
    {
        $client = static::createStub(Client::class);
        $indices = $this->createMock(IndicesNamespace::class);
        $indices->method('existsAlias')->willReturn(true);
        $indices
            ->method('getAlias')
            ->willReturn([
                'sw-admin-promotion-listing_1000' => [
                    'aliases' => [
                        'sw-admin-promotion-listing' => [],
                    ],
                ],
            ]);
        $indices
            ->expects($this->once())
            ->method('putAlias')
            ->with(['index' => 'sw-admin-promotion-listing_3000', 'name' => 'sw-admin-promotion-listing']);
        $indices
            ->expects($this->once())
            ->method('delete')
            ->with(['index' => 'sw-admin-promotion-listing_1000']);

        $client->method('indices')->willReturn($indices);

        // both runs finished before either was promoted; the rows arrive ordered by index name
        $connection = static::createStub(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([
            ['alias' => 'sw-admin-promotion-listing', 'index' => 'sw-admin-promotion-listing_2000'],
            ['alias' => 'sw-admin-promotion-listing', 'index' => 'sw-admin-promotion-listing_3000'],
        ]);

        $registry = $this->createRegistry(['promotion' => $this->indexer], $client, $connection);

        $registry->swapFinishedAliases();
    }

    public function testSwapFinishedAliasesContinuesAfterAnAliasThatDoesNotExistYet(): void
    {
        $client = static::createStub(Client::class);
        $indices = $this->createMock(IndicesNamespace::class);
        // only the second alias exists, so the first one takes the "alias is missing" branch
        $indices
            ->method('existsAlias')
            ->willReturnCallback(static fn (array $arguments): bool => $arguments['name'] === 'sw-admin-order-listing');
        $indices
            ->method('getAlias')
            ->willReturn([
                'sw-admin-order-listing_12345' => [
                    'aliases' => [
                        'sw-admin-order-listing' => [],
                    ],
                ],
            ]);
        $indices
            ->expects($this->once())
            ->method('delete')
            ->with(['index' => 'sw-admin-order-listing_12345']);

        $client->method('indices')->willReturn($indices);

        $connection = static::createStub(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([
            ['alias' => 'sw-admin-promotion-listing', 'index' => 'sw-admin-promotion-listing_67890'],
            ['alias' => 'sw-admin-order-listing', 'index' => 'sw-admin-order-listing_67890'],
        ]);

        $registry = $this->createRegistry(['promotion' => $this->indexer], $client, $connection);

        $registry->swapFinishedAliases();
    }

    /**
     * @param array{index: array{number_of_shards: int|null, number_of_replicas: int|null, test?: int}} $constructorConfig
     */
    #[DataProvider('providerCreateIndices')]
    public function testIterate(array $constructorConfig): void
    {
        $this->indexer->method('getName')->willReturn('promotion-listing');

        $client = static::createStub(Client::class);
        $indices = $this->createMock(IndicesNamespace::class);
        $indices
            ->expects($this->once())
            ->method('existsAlias')
            ->with(['name' => 'sw-admin-promotion-listing']);

        $client->method('indices')->willReturn($indices);

        $connection = static::createStub(Connection::class);

        $searchHelper = new AdminElasticsearchHelper(true, false, 'sw-admin', 'test', true, new NullLogger());
        $registry = new AdminSearchRegistry(
            ['promotion' => $this->indexer],
            $connection,
            static::createStub(MessageBusInterface::class),
            static::createStub(EventDispatcherInterface::class),
            $client,
            $searchHelper,
            static::createStub(LoggerInterface::class),
            ['settings' => $constructorConfig],
            [],
            'test',
            new NativeClock()
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

        $client = static::createStub(Client::class);
        $indices = $this->createMock(IndicesNamespace::class);
        $indices
            ->expects($this->once())
            ->method('existsAlias')
            ->with(['name' => 'sw-admin-promotion-listing']);

        $client->method('indices')->willReturn($indices);

        $eventDispatcher = new EventDispatcher();
        $queue = static::createStub(MessageBusInterface::class);
        $connection = static::createStub(Connection::class);
        $connection->method('fetchAllKeyValue')->willReturn(['sw-admin-promotion-listing' => 'sw-admin-promotion-listing_12345']);

        $searchHelper = new AdminElasticsearchHelper(true, false, 'sw-admin', 'test', true, new NullLogger());
        $index = new AdminSearchRegistry(
            ['promotion' => $this->indexer],
            $connection,
            $queue,
            $eventDispatcher,
            $client,
            $searchHelper,
            static::createStub(LoggerInterface::class),
            [],
            [],
            'test',
            new NativeClock()
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

        $connection = static::createStub(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([
            ['alias' => 'sw-admin-promotion-listing', 'index' => 'sw-admin-promotion-listing_12345'],
        ]);

        $searchHelper = new AdminElasticsearchHelper(true, $refreshIndices, 'sw-admin', 'test', true, new NullLogger());
        $queue = static::createStub(MessageBusInterface::class);

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
                        'completion' => [],
                        'id' => 'c1a28776116d4431a2208eb2960ec340',
                    ],
                ],
            ]);

        $index = new AdminSearchRegistry(
            ['promotion' => $this->indexer],
            $connection,
            $queue,
            static::createStub(EventDispatcherInterface::class),
            $client,
            $searchHelper,
            static::createStub(LoggerInterface::class),
            [],
            [],
            'test',
            new NativeClock()
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

    public function testInvokeCountsDownTheIndexingTaskOfAnIndexingRun(): void
    {
        $this->indexer->method('getName')->willReturn('promotion-listing');
        $this->indexer->method('getEntity')->willReturn('promotion');
        $this->indexer->method('fetch')->willReturn([
            'c1a28776116d4431a2208eb2960ec340' => ['id' => 'c1a28776116d4431a2208eb2960ec340', 'text' => 'a'],
            'c1a28776116d4431a2208eb2960ec341' => ['id' => 'c1a28776116d4431a2208eb2960ec341', 'text' => 'b'],
        ]);

        $client = static::createStub(Client::class);
        $client->method('bulk')->willReturn(['errors' => false, 'items' => []]);

        $statements = [];
        $connection = static::createStub(Connection::class);
        $connection
            ->method('executeStatement')
            ->willReturnCallback(function (string $sql, array $params = []) use (&$statements): int {
                $statements[] = $params;

                return 1;
            });

        $registry = $this->createRegistry(['promotion' => $this->indexer], $client, $connection);

        $registry->__invoke(new AdminSearchIndexingMessage(
            'promotion',
            'promotion-listing',
            ['sw-admin-promotion-listing' => 'sw-admin-promotion-listing_12345'],
            ['c1a28776116d4431a2208eb2960ec340', 'c1a28776116d4431a2208eb2960ec341'],
            [],
            true
        ));

        static::assertSame(
            [['count' => 2, 'index' => 'sw-admin-promotion-listing_12345']],
            $statements
        );
    }

    public function testInvokeDoesNotCountDownForLiveUpdates(): void
    {
        $this->indexer->method('getName')->willReturn('promotion-listing');
        $this->indexer->method('getEntity')->willReturn('promotion');
        $this->indexer->method('fetch')->willReturn([
            'c1a28776116d4431a2208eb2960ec340' => ['id' => 'c1a28776116d4431a2208eb2960ec340', 'text' => 'a'],
        ]);

        $client = static::createStub(Client::class);
        $client->method('bulk')->willReturn(['errors' => false, 'items' => []]);

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('executeStatement');

        $registry = $this->createRegistry(['promotion' => $this->indexer], $client, $connection);

        $registry->__invoke(new AdminSearchIndexingMessage(
            'promotion',
            'promotion-listing',
            ['sw-admin-promotion-listing' => 'sw-admin-promotion-listing_12345'],
            ['c1a28776116d4431a2208eb2960ec340']
        ));
    }

    public function testInvokeDoesNotCountDownWhenTheBulkFailed(): void
    {
        $this->indexer->method('getName')->willReturn('promotion-listing');
        $this->indexer->method('getEntity')->willReturn('promotion');
        $this->indexer->method('fetch')->willReturn([
            'c1a28776116d4431a2208eb2960ec340' => ['id' => 'c1a28776116d4431a2208eb2960ec340', 'text' => 'a'],
        ]);

        $client = static::createStub(Client::class);
        $client->method('bulk')->willReturn([
            'errors' => true,
            'items' => [
                ['index' => ['_index' => 'sw-admin-promotion-listing_12345', '_id' => 'c1a28776116d4431a2208eb2960ec340', 'status' => 400, 'error' => ['type' => 'mapper_parsing_exception', 'reason' => 'broken']]],
            ],
        ]);

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('executeStatement');

        $registry = $this->createRegistry(['promotion' => $this->indexer], $client, $connection);

        $this->expectException(ElasticsearchException::class);

        $registry->__invoke(new AdminSearchIndexingMessage(
            'promotion',
            'promotion-listing',
            ['sw-admin-promotion-listing' => 'sw-admin-promotion-listing_12345'],
            ['c1a28776116d4431a2208eb2960ec340'],
            [],
            true
        ));
    }

    public function testRefreshWritesToBothTheLiveAndTheIndexBeingBuilt(): void
    {
        $this->indexer->method('getName')->willReturn('promotion-listing');
        $this->indexer->method('getEntity')->willReturn('promotion');
        $this->indexer->method('getUpdatedIds')->willReturn(['c1a28776116d4431a2208eb2960ec340']);
        $this->indexer->method('fetch')->willReturn([
            'c1a28776116d4431a2208eb2960ec340' => ['id' => 'c1a28776116d4431a2208eb2960ec340', 'text' => 'a'],
        ]);

        $written = [];
        $client = $this->createMock(Client::class);
        $client
            ->expects($this->exactly(2))
            ->method('bulk')
            ->willReturnCallback(function (array $arguments) use (&$written): array {
                $written[] = $arguments['index'];

                return ['errors' => false, 'items' => []];
            });

        $connection = static::createStub(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([
            ['alias' => 'sw-admin-promotion-listing', 'index' => 'sw-admin-promotion-listing_12345'],
            ['alias' => 'sw-admin-promotion-listing', 'index' => 'sw-admin-promotion-listing_67890'],
        ]);

        $registry = $this->createRegistry(['promotion' => $this->indexer], $client, $connection);

        $registry->refresh(new EntityWrittenContainerEvent(Context::createDefaultContext(), new NestedEventCollection([
            new EntityWrittenEvent('promotion', [
                new EntityWriteResult('c1a28776116d4431a2208eb2960ec340', [], 'promotion', EntityWriteResult::OPERATION_INSERT),
            ], Context::createDefaultContext()),
        ]), []));

        static::assertSame(
            ['sw-admin-promotion-listing_12345', 'sw-admin-promotion-listing_67890'],
            $written
        );
    }

    public function testRefreshQueuesEveryAffectedIndexerForSalesChannelSources(): void
    {
        $this->indexer->method('getName')->willReturn('promotion-listing');
        $this->indexer->method('getEntity')->willReturn('promotion');
        $this->indexer->method('getUpdatedIds')->willReturn(['c1a28776116d4431a2208eb2960ec340']);

        $secondIndexer = static::createStub(AbstractAdminIndexer::class);
        $secondIndexer->method('getName')->willReturn('order-listing');
        $secondIndexer->method('getEntity')->willReturn('order');
        $secondIndexer->method('getUpdatedIds')->willReturn(['a1a28776116d4431a2208eb2960ec341']);

        $connection = static::createStub(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([
            ['alias' => 'sw-admin-promotion-listing', 'index' => 'sw-admin-promotion-listing_12345'],
            ['alias' => 'sw-admin-order-listing', 'index' => 'sw-admin-order-listing_12345'],
        ]);

        $dispatched = [];
        $queue = $this->createMock(MessageBusInterface::class);
        $queue
            ->expects($this->exactly(2))
            ->method('dispatch')
            ->willReturnCallback(function (object $message) use (&$dispatched): Envelope {
                static::assertInstanceOf(AdminSearchIndexingMessage::class, $message);
                $dispatched[] = $message->getEntity();

                return new Envelope($message);
            });

        $searchHelper = new AdminElasticsearchHelper(true, false, 'sw-admin', 'test', true, new NullLogger());
        $registry = new AdminSearchRegistry(
            ['promotion' => $this->indexer, 'order' => $secondIndexer],
            $connection,
            $queue,
            static::createStub(EventDispatcherInterface::class),
            static::createStub(Client::class),
            $searchHelper,
            static::createStub(LoggerInterface::class),
            [],
            [],
            'test',
            new NativeClock()
        );

        $context = Context::createDefaultContext(new SalesChannelApiSource(TestDefaults::SALES_CHANNEL));

        $registry->refresh(new EntityWrittenContainerEvent($context, new NestedEventCollection([
            new EntityWrittenEvent('promotion', [
                new EntityWriteResult('c1a28776116d4431a2208eb2960ec340', [], 'promotion', EntityWriteResult::OPERATION_INSERT),
            ], $context),
            new EntityWrittenEvent('order', [
                new EntityWriteResult('a1a28776116d4431a2208eb2960ec341', [], 'order', EntityWriteResult::OPERATION_INSERT),
            ], $context),
        ]), []));

        static::assertSame(['promotion', 'order'], $dispatched);
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
            static::createStub(Connection::class),
            static::createStub(MessageBusInterface::class),
            static::createStub(EventDispatcherInterface::class),
            $client,
            $searchHelper,
            static::createStub(LoggerInterface::class),
            [],
            [],
            'test',
            new NativeClock()
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
        $indexer = $this->createMock(AbstractAdminIndexer::class);
        $indexer->method('getName')->willReturn('promotion-listing');
        $indexer->method('getEntity')->willReturn('promotion');
        $indexer->expects($this->never())->method('fetch');

        $client = $this->createMock(Client::class);
        $client->expects($this->never())->method('bulk');

        $client->method('indices')->willThrowException(new RuntimeException('no nodes'));

        $connection = static::createStub(Connection::class);

        $searchHelper = new AdminElasticsearchHelper(true, true, 'sw-admin', 'test', true, new NullLogger());
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('error')
            ->with('Could not refresh indices. Run "bin/console es:admin:mapping:update" & "bin/console es:admin:index" to update indices and reindex. Error: no nodes');

        $index = new AdminSearchRegistry(
            ['promotion' => $indexer],
            $connection,
            static::createStub(MessageBusInterface::class),
            static::createStub(EventDispatcherInterface::class),
            $client,
            $searchHelper,
            $logger,
            [],
            [],
            'test',
            new NativeClock()
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
        $client = static::createStub(Client::class);
        $indices = $this->createMock(IndicesNamespace::class);
        $indices->expects($this->never())->method('existsAlias');

        $client->method('indices')->willReturn($indices);

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('executeStatement');

        $searchHelper = new AdminElasticsearchHelper(true, true, 'sw-admin', 'test', true, new NullLogger());
        $index = new AdminSearchRegistry(
            [],
            $connection,
            static::createStub(MessageBusInterface::class),
            static::createStub(EventDispatcherInterface::class),
            $client,
            $searchHelper,
            static::createStub(LoggerInterface::class),
            [],
            [],
            'test',
            new NativeClock()
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
                        'completion' => [],
                        'id' => 'c1a28776116d4431a2208eb2960ec340',
                    ],
                ],
            ]);

        $indices = ['sw-admin-promotion-listing' => 'sw-admin-promotion-listing_12345'];

        $searchHelper = new AdminElasticsearchHelper(true, false, 'sw-admin', 'test', true, new NullLogger());
        $index = new AdminSearchRegistry(
            ['promotion' => $this->indexer],
            static::createStub(Connection::class),
            static::createStub(MessageBusInterface::class),
            static::createStub(EventDispatcherInterface::class),
            $client,
            $searchHelper,
            static::createStub(LoggerInterface::class),
            [],
            [],
            'test',
            new NativeClock()
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

        $client = static::createStub(Client::class);
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
            static::createStub(Connection::class),
            static::createStub(MessageBusInterface::class),
            static::createStub(EventDispatcherInterface::class),
            $client,
            $searchHelper,
            static::createStub(LoggerInterface::class),
            [],
            [],
            'test',
            new NativeClock()
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
        yield 'refresh indices' => [true];
        yield 'do not refresh indices' => [false];
    }

    /**
     * @param array<string, AbstractAdminIndexer> $indexers
     */
    private function createRegistry(array $indexers, Client $client, Connection $connection): AdminSearchRegistry
    {
        return new AdminSearchRegistry(
            $indexers,
            $connection,
            static::createStub(MessageBusInterface::class),
            static::createStub(EventDispatcherInterface::class),
            $client,
            new AdminElasticsearchHelper(true, false, 'sw-admin', 'test', true, new NullLogger()),
            static::createStub(LoggerInterface::class),
            [],
            [],
            'test',
            new NativeClock()
        );
    }
}
