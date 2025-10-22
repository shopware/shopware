<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\DataAbstractionLayer;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\DataAbstractionLayer\ProductStreamMappingIndexingMessage;
use Shopware\Core\Content\Product\DataAbstractionLayer\ProductStreamUpdater;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\ProductStream\Aggregate\ProductStreamFilter\ProductStreamFilterDefinition;
use Shopware\Core\Content\ProductStream\ProductStreamDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Exception\UnmappedFieldException;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\ManyToManyIdFieldUpdater;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\Event\NestedEventCollection;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @internal
 */
#[CoversClass(ProductStreamUpdater::class)]
class ProductStreamUpdaterTest extends TestCase
{
    public function testUpdaterCanBeDisabled(): void
    {
        $connectionMock = $this->createMock(Connection::class);
        $connectionMock->expects($this->never())->method(static::anything());

        $messageBusMock = $this->createMock(MessageBusInterface::class);
        $messageBusMock->expects($this->never())->method(static::anything());

        /** @var StaticEntityRepository<ProductCollection> $repo */
        $repo = new StaticEntityRepository([]);

        $updater = new ProductStreamUpdater(
            $connectionMock,
            new ProductDefinition(),
            $repo,
            $messageBusMock,
            $this->createMock(ManyToManyIdFieldUpdater::class),
            false
        );

        $containerEvent = new EntityWrittenContainerEvent(
            Context::createCLIContext(),
            new NestedEventCollection([
                new EntityWrittenEvent('product_stream', [
                    new EntityWriteResult('product-1', [], 'test', EntityWriteResult::OPERATION_UPDATE),
                ], Context::createCLIContext()),
            ]),
            []
        );

        $updater->updateProducts(['1', '2'], Context::createDefaultContext());
        $updater->update($containerEvent);
    }

    public function testUpdaterWithFilterChange(): void
    {
        $connectionMock = $this->createMock(Connection::class);
        $messageBusMock = $this->createMock(MessageBusInterface::class);
        $messageBusMock->expects($this->once())->method('dispatch')->willReturnCallback(function ($message) {
            static::assertInstanceOf(ProductStreamMappingIndexingMessage::class, $message);
            static::assertSame('product-stream-1', $message->getData());
            static::assertSame('product_stream_mapping.indexer', $message->getIndexer());

            return new Envelope($message);
        });

        /** @var StaticEntityRepository<ProductCollection> $repo */
        $repo = new StaticEntityRepository([]);

        $updater = new ProductStreamUpdater(
            $connectionMock,
            new ProductDefinition(),
            $repo,
            $messageBusMock,
            $this->createMock(ManyToManyIdFieldUpdater::class),
            true
        );

        $containerEvent = new EntityWrittenContainerEvent(
            Context::createCLIContext(),
            new NestedEventCollection([
                new EntityWrittenEvent(ProductStreamDefinition::ENTITY_NAME, [
                    new EntityWriteResult('product-stream-1', [], ProductStreamDefinition::ENTITY_NAME, EntityWriteResult::OPERATION_UPDATE),
                ], Context::createCLIContext()),
                new EntityWrittenEvent(ProductStreamFilterDefinition::ENTITY_NAME, [
                    new EntityWriteResult('product-stream-filter-1', [
                        'operator' => 'and',
                    ], ProductStreamFilterDefinition::ENTITY_NAME, EntityWriteResult::OPERATION_UPDATE),
                ], Context::createCLIContext()),
            ]),
            []
        );

        $updater->update($containerEvent);
    }

    public function testUpdaterWithoutFilterChange(): void
    {
        $connectionMock = $this->createMock(Connection::class);

        $messageBusMock = $this->createMock(MessageBusInterface::class);
        $messageBusMock->expects($this->never())->method('dispatch');

        /** @var StaticEntityRepository<ProductCollection> $repo */
        $repo = new StaticEntityRepository([]);

        $updater = new ProductStreamUpdater(
            $connectionMock,
            new ProductDefinition(),
            $repo,
            $messageBusMock,
            $this->createMock(ManyToManyIdFieldUpdater::class),
            true
        );

        $containerEvent = new EntityWrittenContainerEvent(
            Context::createCLIContext(),
            new NestedEventCollection([
                new EntityWrittenEvent(ProductStreamDefinition::ENTITY_NAME, [
                    new EntityWriteResult('product-1', [], 'test', EntityWriteResult::OPERATION_UPDATE),
                ], Context::createCLIContext()),
            ]),
            []
        );

        static::assertNull($updater->update($containerEvent));
    }

    /**
     * @param string[] $ids
     * @param array<int, array<string, bool|string>> $filters
     */
    #[DataProvider('filterProvider')]
    public function testCriteriaWithUpdateProducts(array $ids, array $filters, Criteria $criteria): void
    {
        $context = Context::createDefaultContext();

        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('fetchAllAssociative')
            ->willReturn($filters);

        $criteria->addFilter(new EqualsAnyFilter('id', $ids));

        /** @var StaticEntityRepository<ProductCollection> */
        $repository = new StaticEntityRepository([
            function (Criteria $actualCriteria, Context $actualContext) use ($criteria, $context, $ids): array {
                static::assertEquals($criteria, $actualCriteria);
                static::assertEquals($context, $actualContext);

                return $ids;
            },
        ]);

        $updater = new ProductStreamUpdater(
            $connection,
            new ProductDefinition(),
            $repository,
            $this->createMock(MessageBusInterface::class),
            $this->createMock(ManyToManyIdFieldUpdater::class),
            true
        );

        $updater->updateProducts($ids, $context);
    }

    /**
     * @param string[] $ids
     * @param array<int, array<string, bool|string>> $filters
     */
    #[DataProvider('filterProvider')]
    public function testCriteriaWithHandle(array $ids, array $filters, Criteria $criteria): void
    {
        $context = Context::createDefaultContext();
        $context->setConsiderInheritance(true);

        $message = new ProductStreamMappingIndexingMessage(Uuid::randomHex());

        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('fetchOne')
            ->willReturn(current(array_column($filters, 'api_filter')));

        $connection
            ->expects($this->once())
            ->method('fetchFirstColumn')
            ->willReturn($ids);

        $connection
            ->expects($this->exactly(2))
            ->method('transactional')
            ->withAnyParameters();

        $definition = new ProductDefinition();
        $newMatches = [Uuid::randomHex(), Uuid::randomHex()];
        /** @var StaticEntityRepository<ProductCollection> */
        $repository = new StaticEntityRepository([
            function (Criteria $actualCriteria, Context $actualContext) use ($criteria, $context, $newMatches): array {
                static::assertTrue($actualCriteria->hasState(Criteria::STATE_ELASTICSEARCH_AWARE));
                $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);

                static::assertEquals($criteria, $actualCriteria);
                static::assertEquals($context, $actualContext);

                return $newMatches;
            },
            fn () => [],
        ], $definition);

        $manyToManyFieldUpdater = $this->createMock(ManyToManyIdFieldUpdater::class);

        $ids = [...$newMatches, ...$ids];
        $manyToManyFieldUpdater
            ->expects($this->once())
            ->method('update')
            ->with($definition->getEntityName(), $ids, Context::createDefaultContext(), 'streamIds');

        $updater = new ProductStreamUpdater(
            $connection,
            $definition,
            $repository,
            $this->createMock(MessageBusInterface::class),
            $manyToManyFieldUpdater,
            true
        );

        $updater->handle($message);
    }

    /**
     * @param string[] $oldMatches
     * @param string[] $newMatches
     * @param string[] $manyToManyUpdatedIds
     */
    #[DataProvider('transactionalProvider')]
    public function testTransactionalHandle(array $oldMatches, array $newMatches, array $manyToManyUpdatedIds, int $numOfTransactional): void
    {
        $context = Context::createDefaultContext();
        $context->setConsiderInheritance(true);

        $message = new ProductStreamMappingIndexingMessage(Uuid::randomHex());

        $filters = json_encode([[
            'type' => 'equals',
            'field' => 'active',
            'value' => '1',
        ]]);

        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('fetchOne')
            ->willReturn($filters);

        $connection
            ->expects($this->once())
            ->method('fetchFirstColumn')
            ->willReturn($oldMatches);

        $connection
            ->expects($this->exactly($numOfTransactional))
            ->method('transactional')
            ->withAnyParameters();

        $criteria = new Criteria();
        $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);
        $criteria->addFilter(new EqualsFilter('product.active', true));

        $definition = new ProductDefinition();
        /** @var StaticEntityRepository<ProductCollection> */
        $repository = new StaticEntityRepository([
            function (Criteria $actualCriteria, Context $actualContext) use ($criteria, $context, $newMatches): array {
                static::assertEquals($criteria, $actualCriteria);
                static::assertEquals($context, $actualContext);

                return $newMatches;
            },
            fn () => [],
        ], $definition);

        $manyToManyFieldUpdater = $this->createMock(ManyToManyIdFieldUpdater::class);

        $manyToManyFieldUpdater
            ->expects(empty($manyToManyUpdatedIds) ? $this->never() : $this->once())
            ->method('update')
            ->with($definition->getEntityName(), $manyToManyUpdatedIds, Context::createDefaultContext(), 'streamIds');

        $updater = new ProductStreamUpdater(
            $connection,
            $definition,
            $repository,
            $this->createMock(MessageBusInterface::class),
            $manyToManyFieldUpdater,
            true
        );

        $updater->handle($message);
    }

    public function testInvalidFilter(): void
    {
        $context = Context::createDefaultContext();
        $context->setConsiderInheritance(true);

        $message = new ProductStreamMappingIndexingMessage(Uuid::randomHex());

        $filters = json_encode([[
            'type' => 'equals',
            'field' => 'active',
            'value' => '1',
        ]]);

        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('fetchOne')
            ->willReturn($filters);

        $oldMatches = [Uuid::randomHex(), Uuid::randomHex()];
        $connection
            ->expects($this->once())
            ->method('fetchFirstColumn')
            ->willReturn($oldMatches);

        $connection
            ->expects($this->exactly(1)) // delete only
            ->method('transactional')
            ->withAnyParameters();

        $criteria = new Criteria();
        $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);
        $criteria->addFilter(new EqualsFilter('product.active', true));

        $definition = new ProductDefinition();
        /** @var StaticEntityRepository<ProductCollection> */
        $repository = new StaticEntityRepository([
            function (Criteria $actualCriteria, Context $actualContext) use ($criteria, $context): array {
                static::assertEquals($criteria, $actualCriteria);
                static::assertEquals($context, $actualContext);

                throw new UnmappedFieldException('non-existing-field', $this->createMock(ProductDefinition::class));
            },
            fn () => [],
        ], $definition);

        $manyToManyFieldUpdater = $this->createMock(ManyToManyIdFieldUpdater::class);

        $manyToManyFieldUpdater
            ->expects($this->once())
            ->method('update')
            ->with($definition->getEntityName(), $oldMatches, Context::createDefaultContext(), 'streamIds');

        $updater = new ProductStreamUpdater(
            $connection,
            $definition,
            $repository,
            $this->createMock(MessageBusInterface::class),
            $manyToManyFieldUpdater,
            true
        );

        $updater->handle($message);
    }

    /**
     * @return iterable<string, array<int, array<int, array<string, bool|string>|string>|Criteria>>
     */
    public static function filterProvider(): iterable
    {
        $id = Uuid::randomHex();

        yield 'Active filter' => [
            [$id],
            [
                [
                    'id' => Uuid::randomHex(),
                    'api_filter' => json_encode([[
                        'type' => 'equals',
                        'field' => 'active',
                        'value' => '1',
                    ]]),
                ],
            ],
            (new Criteria())->addFilter(
                new EqualsFilter('product.active', true),
            ),
        ];

        yield 'Price filter' => [
            [$id],
            [
                [
                    'id' => Uuid::randomHex(),
                    'api_filter' => json_encode([[
                        'type' => 'range',
                        'field' => 'product.cheapestPrice',
                        'parameters' => [
                            'lte' => 50,
                        ],
                    ]]),
                ],
            ],
            (new Criteria())->addFilter(
                new MultiFilter(MultiFilter::CONNECTION_OR, [
                    new RangeFilter('product.price', [RangeFilter::LTE => 50]),
                    new RangeFilter('product.prices.price', [RangeFilter::LTE => 50]),
                ]),
            ),
        ];

        yield 'Nested price filter' => [
            [$id],
            [
                [
                    'id' => Uuid::randomHex(),
                    'api_filter' => json_encode([[
                        'type' => 'multi',
                        'operator' => 'AND',
                        'queries' => [[
                            'type' => 'range',
                            'field' => 'product.cheapestPrice',
                            'parameters' => [
                                'lte' => 50,
                            ],
                        ]],
                    ]]),
                ],
            ],
            (new Criteria())->addFilter(
                new MultiFilter(MultiFilter::CONNECTION_AND, [
                    new MultiFilter(MultiFilter::CONNECTION_OR, [
                        new RangeFilter('product.price', [RangeFilter::LTE => 50]),
                        new RangeFilter('product.prices.price', [RangeFilter::LTE => 50]),
                    ]),
                ]),
            ),
        ];

        yield 'Nested price percentage filter' => [
            [$id],
            [
                [
                    'id' => Uuid::randomHex(),
                    'api_filter' => json_encode([[
                        'type' => 'multi',
                        'operator' => 'AND',
                        'queries' => [[
                            'type' => 'range',
                            'field' => 'cheapestPrice.percentage',
                            'parameters' => [
                                'lte' => 50,
                            ],
                        ]],
                    ]]),
                ],
            ],
            (new Criteria())->addFilter(
                new MultiFilter(MultiFilter::CONNECTION_AND, [
                    new MultiFilter(MultiFilter::CONNECTION_OR, [
                        new RangeFilter('product.price.percentage', [RangeFilter::LTE => 50]),
                        new RangeFilter('product.prices.price.percentage', [RangeFilter::LTE => 50]),
                    ]),
                ]),
            ),
        ];
    }

    /**
     * @return iterable<string, array{oldMatches: list<string>, newMatches: list<string>, numOfTransactional: int, manyToManyUpdatedIds: list<string>}>
     */
    public static function transactionalProvider(): iterable
    {
        $productId1 = Uuid::randomHex();
        $productId2 = Uuid::randomHex();
        $productId3 = Uuid::randomHex();
        $productId4 = Uuid::randomHex();
        $productId5 = Uuid::randomHex();

        yield 'Both empty old and new matches' => [
            'oldMatches' => [],
            'newMatches' => [],
            'numOfTransactional' => 0, // no change
            'manyToManyUpdatedIds' => [],
        ];

        yield 'Empty old matches' => [
            'oldMatches' => [],
            'newMatches' => [$productId3, $productId4, $productId5],
            'numOfTransactional' => 1, // only add,
            'manyToManyUpdatedIds' => [$productId3, $productId4, $productId5],
        ];

        yield 'Empty new matches' => [
            'oldMatches' => [$productId1, $productId2],
            'newMatches' => [],
            'numOfTransactional' => 1, // only delete,
            'manyToManyUpdatedIds' => [$productId1, $productId2],
        ];

        yield 'Same old and new matches' => [
            'oldMatches' => [$productId1, $productId2],
            'newMatches' => [$productId1, $productId2],
            'numOfTransactional' => 0, // no change
            'manyToManyUpdatedIds' => [],
        ];

        yield 'Some old and new matches' => [
            'oldMatches' => [$productId1, $productId2],
            'newMatches' => [$productId2, $productId3],
            'numOfTransactional' => 2, // add and delete
            'manyToManyUpdatedIds' => [$productId3, $productId1],
        ];

        yield 'All different old and new matches' => [
            'oldMatches' => [$productId1, $productId2],
            'newMatches' => [$productId3, $productId4, $productId5],
            'numOfTransactional' => 2, // add and delete
            'manyToManyUpdatedIds' => [$productId3, $productId4, $productId5, $productId1, $productId2],
        ];
    }
}
