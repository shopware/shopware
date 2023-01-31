<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Elasticsearch\Admin;

use Doctrine\DBAL\Connection;
use OpenSearch\Client;
use OpenSearch\Namespaces\IndicesNamespace;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IterableQuery;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\Event\NestedEventCollection;
use Shopware\Core\Framework\Event\ProgressAdvancedEvent;
use Shopware\Core\Framework\Event\ProgressFinishedEvent;
use Shopware\Core\Framework\Event\ProgressStartedEvent;
use Shopware\Elasticsearch\Admin\AdminElasticsearchHelper;
use Shopware\Elasticsearch\Admin\AdminIndexingBehavior;
use Shopware\Elasticsearch\Admin\AdminSearchIndexingMessage;
use Shopware\Elasticsearch\Admin\AdminSearchRegistry;
use Shopware\Elasticsearch\Admin\Indexer\AbstractAdminIndexer;
use Shopware\Elasticsearch\Admin\Indexer\PromotionAdminSearchIndexer;
use Shopware\Elasticsearch\Exception\ElasticsearchIndexingException;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @package system-settings
 *
 * @internal
 *
 * @covers \Shopware\Elasticsearch\Admin\AdminSearchRegistry
 */
class AdminSearchRegistryTest extends TestCase
{
    private MockObject&AbstractAdminIndexer $indexer;

    public function setUp(): void
    {
        $this->indexer = $this->getMockBuilder(PromotionAdminSearchIndexer::class)->disableOriginalConstructor()->getMock();
    }

    public function testGetHandledMessages(): void
    {
        static::assertEquals([AdminSearchIndexingMessage::class], AdminSearchRegistry::getHandledMessages());
    }

    public function testGetSubscribedEvents(): void
    {
        $events = AdminSearchRegistry::getSubscribedEvents();

        static::assertArrayHasKey(EntityWrittenContainerEvent::class, $events);
    }

    public function testGetIndexers(): void
    {
        $searchHelper = new AdminElasticsearchHelper(true, false, 'sw-admin');
        $registry = new AdminSearchRegistry(
            ['promotion' => $this->indexer],
            $this->createMock(Connection::class),
            $this->createMock(MessageBusInterface::class),
            $this->createMock(EventDispatcherInterface::class),
            $this->createMock(Client::class),
            $searchHelper,
            [],
            []
        );
        $indexers = $registry->getIndexers();

        static::assertSame(['promotion' => $this->indexer], $indexers);
    }

    public function testGetIndexerWithInvalidName(): void
    {
        $searchHelper = new AdminElasticsearchHelper(true, false, 'sw-admin');
        $registry = new AdminSearchRegistry(
            ['promotion' => $this->indexer],
            $this->createMock(Connection::class),
            $this->createMock(MessageBusInterface::class),
            $this->createMock(EventDispatcherInterface::class),
            $this->createMock(Client::class),
            $searchHelper,
            [],
            []
        );
        static::expectException(ElasticsearchIndexingException::class);
        $registry->getIndexer('test');
    }

    public function testGetIndexer(): void
    {
        $searchHelper = new AdminElasticsearchHelper(true, false, 'sw-admin');
        $registry = new AdminSearchRegistry(
            ['promotion' => $this->indexer],
            $this->createMock(Connection::class),
            $this->createMock(MessageBusInterface::class),
            $this->createMock(EventDispatcherInterface::class),
            $this->createMock(Client::class),
            $searchHelper,
            [],
            []
        );
        $indexer = $registry->getIndexer('promotion');

        static::assertSame($this->indexer, $indexer);
    }

    public function testIterateWithExistedAliasWillBeSwap(): void
    {
        $this->indexer->expects(static::any())->method('getName')->willReturn('promotion-listing');

        $client = $this->createMock(Client::class);
        $indices = $this->createMock(IndicesNamespace::class);
        $indices->expects(static::any())->method('existsAlias')->willReturn(true);
        $indices
            ->expects(static::any())
            ->method('getAlias')
            ->willReturn([
                'sw-admin-promotion-listing_12345' => [
                    'aliases' => [
                        'sw-admin-promotion-listing' => [],
                    ],
                ],
            ]);
        $indices
            ->expects(static::once())
            ->method('delete')
            ->with(['index' => 'sw-admin-promotion-listing_12345']);

        $client->method('indices')->willReturn($indices);

        $searchHelper = new AdminElasticsearchHelper(true, false, 'sw-admin');
        $registry = new AdminSearchRegistry(
            ['promotion' => $this->indexer],
            $this->createMock(Connection::class),
            $this->createMock(MessageBusInterface::class),
            $this->createMock(EventDispatcherInterface::class),
            $client,
            $searchHelper,
            [],
            []
        );

        $registry->iterate(new AdminIndexingBehavior(false));
    }

    /**
     * @param array<mixed> $constructorConfig
     *
     * @dataProvider providerCreateIndices
     */
    public function testIterate(array $constructorConfig): void
    {
        $this->indexer->expects(static::any())->method('getName')->willReturn('promotion-listing');

        $client = $this->createMock(Client::class);
        $indices = $this->createMock(IndicesNamespace::class);
        $indices
            ->expects(static::exactly(2))
            ->method('existsAlias')
            ->with(['name' => 'sw-admin-promotion-listing']);

        $client->method('indices')->willReturn($indices);

        $connection = $this->createMock(Connection::class);
        $connection->expects(static::any())->method('fetchAllKeyValue')->willReturn(['sw-admin-promotion-listing' => 'sw-admin-promotion-listing_12345']);

        $searchHelper = new AdminElasticsearchHelper(true, false, 'sw-admin');
        $registry = new AdminSearchRegistry(
            ['promotion' => $this->indexer],
            $connection,
            $this->createMock(MessageBusInterface::class),
            $this->createMock(EventDispatcherInterface::class),
            $client,
            $searchHelper,
            ['settings' => $constructorConfig],
            []
        );

        $registry->iterate(new AdminIndexingBehavior(true));
    }

    public function testIterateFiresEvents(): void
    {
        $this->indexer->expects(static::any())->method('getName')->willReturn('promotion-listing');
        $this->indexer->expects(static::any())->method('getEntity')->willReturn('promotion');

        $query = $this->createMock(IterableQuery::class);
        $firstRun = true;

        $query->expects(static::exactly(2))->method('fetch')->willReturnCallback(function () use (&$firstRun) {
            if ($firstRun) {
                $firstRun = false;

                return ['1', '2'];
            }

            return [];
        });
        $query->method('fetchCount')->willReturn(2);

        $this->indexer->expects(static::any())->method('getIterator')->willReturn($query);

        $client = $this->createMock(Client::class);
        $indices = $this->createMock(IndicesNamespace::class);
        $indices
            ->expects(static::exactly(2))
            ->method('existsAlias')
            ->with(['name' => 'sw-admin-promotion-listing']);

        $client->method('indices')->willReturn($indices);

        $eventDispatcher = new EventDispatcher();
        $queue = $this->createMock(MessageBusInterface::class);
        $connection = $this->createMock(Connection::class);
        $connection->expects(static::any())->method('fetchAllKeyValue')->willReturn(['sw-admin-promotion-listing' => 'sw-admin-promotion-listing_12345']);

        $searchHelper = new AdminElasticsearchHelper(true, false, 'sw-admin');
        $index = new AdminSearchRegistry(
            ['promotion' => $this->indexer],
            $connection,
            $queue,
            $eventDispatcher,
            $client,
            $searchHelper,
            [],
            []
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
            function (ProgressAdvancedEvent $event) use (&$calledAdvancedEvent): void {
                $calledAdvancedEvent = true;

                static::assertSame(2, $event->getStep());
            }
        );

        $calledFinishEvent = false;
        $eventDispatcher->addListener(
            ProgressFinishedEvent::class,
            function (ProgressFinishedEvent $event) use (&$calledFinishEvent): void {
                $calledFinishEvent = true;

                static::assertSame('promotion-listing', $event->getMessage());
            }
        );

        $index->iterate(new AdminIndexingBehavior(true));

        static::assertTrue($calledStartEvent, 'Event ProgressStartedEvent was not dispatched');
        static::assertTrue($calledAdvancedEvent, 'Event ProgressAdvancedEvent was not dispatched');
        static::assertTrue($calledFinishEvent, 'Event ProgressFinishedEvent was not dispatched');
    }

    /**
     * @dataProvider refreshIndicesProvider
     */
    public function testRefresh(bool $refreshIndices): void
    {
        $this->indexer->expects(static::any())->method('getName')->willReturn('promotion-listing');
        $this->indexer->expects(static::any())->method('getEntity')->willReturn('promotion');
        $this->indexer->expects(static::any())->method('fetch')->willReturn([
            'c1a28776116d4431a2208eb2960ec340' => [
                'id' => 'c1a28776116d4431a2208eb2960ec340',
                'text' => 'c1a28776116d4431a2208eb2960ec340 elasticsearch',
            ],
        ]);

        $client = $this->createMock(Client::class);
        $client
            ->expects(static::once())
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

        if ($refreshIndices) {
            $indices = $this->createMock(IndicesNamespace::class);
            $indices
                ->expects(static::exactly(2))
                ->method('existsAlias')
                ->with(['name' => 'sw-admin-promotion-listing']);

            $client->method('indices')->willReturn($indices);
        }

        $connection = $this->createMock(Connection::class);
        $connection->expects(static::any())->method('fetchAllKeyValue')->willReturn(['sw-admin-promotion-listing' => 'sw-admin-promotion-listing_12345']);

        $searchHelper = new AdminElasticsearchHelper(true, $refreshIndices, 'sw-admin');
        $index = new AdminSearchRegistry(
            ['promotion' => $this->indexer],
            $connection,
            $this->createMock(MessageBusInterface::class),
            $this->createMock(EventDispatcherInterface::class),
            $client,
            $searchHelper,
            [],
            []
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
        $this->indexer->expects(static::any())->method('getName')->willReturn('promotion-listing');
        $this->indexer->expects(static::any())->method('getEntity')->willReturn('promotion');
        $this->indexer->expects(static::any())->method('fetch')->willReturn([
            'c1a28776116d4431a2208eb2960ec340' => [
                'id' => 'c1a28776116d4431a2208eb2960ec340',
                'text' => 'c1a28776116d4431a2208eb2960ec340 elasticsearch',
            ],
        ]);

        $client = $this->createMock(Client::class);
        $client
            ->expects(static::once())
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

        $searchHelper = new AdminElasticsearchHelper(true, false, 'sw-admin');
        $index = new AdminSearchRegistry(
            ['promotion' => $this->indexer],
            $this->createMock(Connection::class),
            $this->createMock(MessageBusInterface::class),
            $this->createMock(EventDispatcherInterface::class),
            $client,
            $searchHelper,
            [],
            []
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
        $this->indexer->expects(static::any())->method('getName')->willReturn('promotion-listing');
        $this->indexer->expects(static::any())->method('getEntity')->willReturn('promotion');
        $this->indexer->expects(static::any())->method('fetch')->willReturn([
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
        $client->expects(static::any())->method('bulk')->willReturn($result);

        $indices = ['sw-admin-promotion-listing' => 'sw-admin-promotion-listing_12345'];

        $searchHelper = new AdminElasticsearchHelper(true, false, 'sw-admin');
        $index = new AdminSearchRegistry(
            ['promotion' => $this->indexer],
            $this->createMock(Connection::class),
            $this->createMock(MessageBusInterface::class),
            $this->createMock(EventDispatcherInterface::class),
            $client,
            $searchHelper,
            [],
            []
        );

        static::expectException(ElasticsearchIndexingException::class);
        $index->__invoke(new AdminSearchIndexingMessage(
            'promotion',
            'promotion',
            $indices,
            ['c1a28776116d4431a2208eb2960ec340']
        ));
    }

    /**
     * @return iterable<array<mixed>>
     */
    public function providerCreateIndices(): iterable
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
    public function refreshIndicesProvider(): iterable
    {
        return [
            [true],
            [false],
        ];
    }
}
