<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Elasticsearch\Admin\Indexer;

use Elasticsearch\Client;
use Elasticsearch\Namespaces\IndicesNamespace;
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
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Elasticsearch\Admin\AdminSearchIndexingMessage;
use Shopware\Elasticsearch\Admin\AdminSearchRegistry;
use Shopware\Elasticsearch\Admin\Indexer\AbstractAdminIndexer;
use Shopware\Elasticsearch\Admin\Indexer\PromotionAdminSearchIndexer;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @internal
 *
 * @covers \Shopware\Elasticsearch\Admin\AdminSearchRegistry
 */
class AdminSearchRegistryTest extends TestCase
{
    /**
     * @var MockObject&AbstractAdminIndexer
     */
    private $indexer;

    public function setUp(): void
    {
        $this->indexer = $this->getMockBuilder(PromotionAdminSearchIndexer::class)->disableOriginalConstructor()->getMock();
    }

    public function testGetIndexers(): void
    {
        $registry = new AdminSearchRegistry(
            ['promotion-listing' => $this->indexer],
            $this->createMock(MessageBusInterface::class),
            $this->createMock(EventDispatcherInterface::class),
            $this->createMock(Client::class),
            $this->createMock(SystemConfigService::class),
            false,
            [],
            []
        );
        $indexers = $registry->getIndexers();

        static::assertSame(['promotion-listing' => $this->indexer], $indexers);
    }

    public function testGetIndexer(): void
    {
        $registry = new AdminSearchRegistry(
            ['promotion-listing' => $this->indexer],
            $this->createMock(MessageBusInterface::class),
            $this->createMock(EventDispatcherInterface::class),
            $this->createMock(Client::class),
            $this->createMock(SystemConfigService::class),
            false,
            [],
            []
        );
        $indexer = $registry->getIndexer('promotion-listing');

        static::assertSame($this->indexer, $indexer);
    }

    /**
     * @param array<mixed> $constructorConfig
     *
     * @dataProvider providerCreateIndices
     */
    public function testIterate(array $constructorConfig): void
    {
        $this->indexer->expects(static::any())->method('getName')->willReturn('promotion-listing');
        $this->indexer->expects(static::any())->method('getIndex')->willReturn('sw-admin-promotion-listing');

        $client = $this->createMock(Client::class);
        $indices = $this->createMock(IndicesNamespace::class);
        $indices
            ->expects(static::exactly(2))
            ->method('existsAlias')
            ->with(['name' => 'sw-admin-promotion-listing']);

        $client->method('indices')->willReturn($indices);

        $index = new AdminSearchRegistry(
            ['promotion-listing' => $this->indexer],
            $this->createMock(MessageBusInterface::class),
            $this->createMock(EventDispatcherInterface::class),
            $client,
            $this->createMock(SystemConfigService::class),
            false,
            ['settings' => $constructorConfig],
            []
        );

        $index->iterate();
    }

    public function testIterateFiresEvents(): void
    {
        $this->indexer->expects(static::any())->method('getName')->willReturn('promotion-listing');
        $this->indexer->expects(static::any())->method('getIndex')->willReturn('sw-admin-promotion-listing');

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
        $index = new AdminSearchRegistry(
            ['promotion-listing' => $this->indexer],
            $queue,
            $eventDispatcher,
            $client,
            $this->createMock(SystemConfigService::class),
            false,
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

        $index->iterate();

        static::assertTrue($calledStartEvent, 'Event ProgressStartedEvent was not dispatched');
        static::assertTrue($calledAdvancedEvent, 'Event ProgressAdvancedEvent was not dispatched');
        static::assertTrue($calledFinishEvent, 'Event ProgressFinishedEvent was not dispatched');
    }

    public function testRefresh(): void
    {
        $this->indexer->expects(static::any())->method('getIndex')->willReturn('sw-admin-promotion-listing');
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
                        'id' => 'c1a28776116d4431a2208eb2960ec340',
                    ],
                ],
            ]);

        $indices = ['sw-admin-promotion-listing' => 'sw-admin-promotion-listing_12345'];
        $systemConfigServices = $this->createMock(SystemConfigService::class);
        $systemConfigServices->expects(static::any())->method('get')->willReturn($indices);

        $index = new AdminSearchRegistry(
            ['promotion-listing' => $this->indexer],
            $this->createMock(MessageBusInterface::class),
            $this->createMock(EventDispatcherInterface::class),
            $client,
            $systemConfigServices,
            false,
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
        $this->indexer->expects(static::any())->method('getIndex')->willReturn('sw-admin-promotion-listing');
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
                        'id' => 'c1a28776116d4431a2208eb2960ec340',
                    ],
                ],
            ]);

        $indices = ['sw-admin-promotion-listing' => 'sw-admin-promotion-listing_12345'];

        $index = new AdminSearchRegistry(
            ['promotion-listing' => $this->indexer],
            $this->createMock(MessageBusInterface::class),
            $this->createMock(EventDispatcherInterface::class),
            $client,
            $this->createMock(SystemConfigService::class),
            false,
            [],
            []
        );

        $index->handle(new AdminSearchIndexingMessage(
            'promotion-listing',
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
}
