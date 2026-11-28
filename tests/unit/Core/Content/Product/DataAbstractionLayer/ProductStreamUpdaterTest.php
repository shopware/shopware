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
use Shopware\Core\Content\Product\ProductException;
use Shopware\Core\Content\ProductStream\Aggregate\ProductStreamFilter\ProductStreamFilterDefinition;
use Shopware\Core\Content\ProductStream\ProductStreamDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\ManyToManyIdFieldUpdater;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\Event\NestedEventCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Language\LanguageCollection;
use Shopware\Core\System\Language\LanguageEntity;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ProductStreamUpdater::class)]
class ProductStreamUpdaterTest extends TestCase
{
    public function testUpdaterCanBeDisabled(): void
    {
        $connectionMock = $this->createMock(Connection::class);
        $connectionMock->expects($this->never())->method(static::anything());

        $messageBusMock = $this->createMock(MessageBusInterface::class);
        $messageBusMock->expects($this->never())->method(static::anything());

        $repo = new StaticEntityRepository([]);

        $languageRepo = new StaticEntityRepository([]);

        $updater = new ProductStreamUpdater(
            $connectionMock,
            new ProductDefinition(),
            $repo,
            $messageBusMock,
            static::createStub(ManyToManyIdFieldUpdater::class),
            $languageRepo,
            false,
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
        $updatedStreamId = Uuid::randomHex();
        $deletedStreamId = Uuid::randomHex();
        $connectionMock = static::createStub(Connection::class);
        $messageBusMock = $this->createMock(MessageBusInterface::class);
        $expectedMessages = [$updatedStreamId, $deletedStreamId];
        $matcher = $this->exactly(\count($expectedMessages));
        $messageBusMock->expects($matcher)->method('dispatch')->willReturnCallback(static function ($message) use ($matcher, $expectedMessages) {
            static::assertInstanceOf(ProductStreamMappingIndexingMessage::class, $message);
            static::assertSame($expectedMessages[$matcher->numberOfInvocations() - 1], $message->getData());
            static::assertSame('product_stream_mapping.indexer', $message->getIndexer());

            return new Envelope($message);
        });

        $repo = new StaticEntityRepository([]);

        $languageRepo = new StaticEntityRepository([]);

        $updater = new ProductStreamUpdater(
            $connectionMock,
            new ProductDefinition(),
            $repo,
            $messageBusMock,
            static::createStub(ManyToManyIdFieldUpdater::class),
            $languageRepo,
            true,
        );

        $containerEvent = new EntityWrittenContainerEvent(
            Context::createCLIContext(),
            new NestedEventCollection([
                new EntityWrittenEvent(ProductStreamFilterDefinition::ENTITY_NAME, [
                    new EntityWriteResult('product-stream-filter-1', [
                        'productStreamId' => $updatedStreamId,
                        'operator' => 'and',
                    ], ProductStreamFilterDefinition::ENTITY_NAME, EntityWriteResult::OPERATION_UPDATE),
                    new EntityWriteResult('product-stream-filter-2', [], ProductStreamFilterDefinition::ENTITY_NAME, EntityWriteResult::OPERATION_DELETE, new EntityExistence(
                        ProductStreamFilterDefinition::ENTITY_NAME,
                        ['id' => Uuid::fromHexToBytes(Uuid::randomHex())],
                        true,
                        false,
                        false,
                        ['product_stream_id' => Uuid::fromHexToBytes($deletedStreamId)]
                    )),
                ], Context::createCLIContext()),
            ]),
            []
        );

        $updater->update($containerEvent);
    }

    public function testUpdaterWithoutFilterChange(): void
    {
        $connectionMock = static::createStub(Connection::class);

        $messageBusMock = $this->createMock(MessageBusInterface::class);
        $messageBusMock->expects($this->never())->method('dispatch');

        $repo = new StaticEntityRepository([]);

        $languageRepo = new StaticEntityRepository([]);

        $updater = new ProductStreamUpdater(
            $connectionMock,
            new ProductDefinition(),
            $repo,
            $messageBusMock,
            static::createStub(ManyToManyIdFieldUpdater::class),
            $languageRepo,
            true,
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

        $repository = StaticEntityRepository::of(ProductCollection::class, [
            static function (Criteria $actualCriteria, Context $context) use ($criteria, $ids): array {
                static::assertEquals($criteria, $actualCriteria);

                return $ids;
            },
        ]);

        $updater = new ProductStreamUpdater(
            $connection,
            new ProductDefinition(),
            $repository,
            static::createStub(MessageBusInterface::class),
            static::createStub(ManyToManyIdFieldUpdater::class),
            $this->createDefaultLanguageRepo(),
            true,
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
        $message = new ProductStreamMappingIndexingMessage(Uuid::randomHex());

        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('fetchAssociative')
            ->willReturn(['invalid' => 0, 'api_filter' => current(array_column($filters, 'api_filter'))]);

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
        $repository = StaticEntityRepository::of(ProductCollection::class, [
            static function (Criteria $actualCriteria, Context $context) use ($criteria, $newMatches): array {
                static::assertTrue($actualCriteria->hasState(Criteria::STATE_ELASTICSEARCH_AWARE));

                static::assertEquals(self::firstPageCriteria($criteria), $actualCriteria);

                return self::page($newMatches);
            },
            static fn () => [],
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
            static::createStub(MessageBusInterface::class),
            $manyToManyFieldUpdater,
            $this->createDefaultLanguageRepo(),
            true,
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
        $message = new ProductStreamMappingIndexingMessage(Uuid::randomHex());

        $filters = json_encode([[
            'type' => 'equals',
            'field' => 'active',
            'value' => '1',
        ]]);

        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('fetchAssociative')
            ->willReturn(['invalid' => 0, 'api_filter' => $filters]);

        $connection
            ->expects($this->once())
            ->method('fetchFirstColumn')
            ->willReturn($oldMatches);

        $connection
            ->expects($this->exactly($numOfTransactional))
            ->method('transactional')
            ->withAnyParameters();

        $criteria = self::firstPageCriteria((new Criteria())->addFilter(new EqualsFilter('product.active', true)));

        $definition = new ProductDefinition();
        $repository = StaticEntityRepository::of(ProductCollection::class, [
            static function (Criteria $actualCriteria, Context $context) use ($criteria, $newMatches): array {
                static::assertEquals($criteria, $actualCriteria);

                return self::page($newMatches);
            },
            static fn () => [],
        ], $definition);

        $manyToManyFieldUpdater = $this->createMock(ManyToManyIdFieldUpdater::class);

        $manyToManyFieldUpdater
            ->expects($manyToManyUpdatedIds === [] ? $this->never() : $this->once())
            ->method('update')
            ->with($definition->getEntityName(), $manyToManyUpdatedIds, Context::createDefaultContext(), 'streamIds');

        $updater = new ProductStreamUpdater(
            $connection,
            $definition,
            $repository,
            static::createStub(MessageBusInterface::class),
            $manyToManyFieldUpdater,
            $this->createDefaultLanguageRepo(),
            true,
        );

        $updater->handle($message);
    }

    public function testInvalidFilter(): void
    {
        $context = Context::createDefaultContext();

        $message = new ProductStreamMappingIndexingMessage(Uuid::randomHex(), null, $context);

        $filters = json_encode([[
            'type' => 'equals',
            'field' => 'active',
            'value' => '1',
        ]]);

        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('fetchAssociative')
            ->willReturn(['invalid' => 0, 'api_filter' => $filters]);

        $oldMatches = [Uuid::randomHex(), Uuid::randomHex()];
        $connection
            ->expects($this->once())
            ->method('fetchFirstColumn')
            ->willReturn($oldMatches);

        $connection
            ->expects($this->exactly(1)) // delete only
            ->method('transactional')
            ->withAnyParameters();

        $criteria = self::firstPageCriteria((new Criteria())->addFilter(new EqualsFilter('product.active', true)));

        $definition = new ProductDefinition();
        $repository = StaticEntityRepository::of(ProductCollection::class, [
            static function (Criteria $actualCriteria, Context $context) use ($criteria): array {
                static::assertEquals($criteria, $actualCriteria);

                throw DataAbstractionLayerException::unmappedField('non-existing-field', new ProductDefinition());
            },
            static fn () => [],
        ], $definition);

        $manyToManyFieldUpdater = $this->createMock(ManyToManyIdFieldUpdater::class);

        $manyToManyFieldUpdater
            ->expects($this->once())
            ->method('update')
            ->with($definition->getEntityName(), $oldMatches, $context, 'streamIds');

        $updater = new ProductStreamUpdater(
            $connection,
            $definition,
            $repository,
            static::createStub(MessageBusInterface::class),
            $manyToManyFieldUpdater,
            $this->createDefaultLanguageRepo(),
            true,
        );

        $updater->handle($message);
    }

    #[DataProvider('searchPageSizeProvider')]
    public function testHandleLoadsStreamPageByPage(?bool $elasticsearchEnabled, ?int $maxResultWindow, int $pageSize): void
    {
        $message = new ProductStreamMappingIndexingMessage(Uuid::randomHex());

        $filters = json_encode([[
            'type' => 'equals',
            'field' => 'active',
            'value' => '1',
        ]]);

        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('fetchAssociative')
            ->willReturn(['invalid' => 0, 'api_filter' => $filters]);

        $connection
            ->expects($this->once())
            ->method('fetchFirstColumn')
            ->willReturn([]);

        $connection
            ->expects($this->once())
            ->method('transactional')
            ->withAnyParameters()
            ->willReturnCallback(static fn (callable $operation) => $operation());

        $firstPage = array_map(static fn () => Uuid::randomHex(), range(1, $pageSize));
        $allMatches = [...$firstPage, Uuid::randomHex()];

        $definition = new ProductDefinition();
        $repository = StaticEntityRepository::of(ProductCollection::class, [
            static function (Criteria $criteria) use ($firstPage, $pageSize): array {
                static::assertTrue($criteria->hasState(Criteria::STATE_ELASTICSEARCH_AWARE));
                static::assertSame($pageSize, $criteria->getLimit());

                return self::page($firstPage);
            },
            static function (Criteria $criteria) use ($allMatches, $pageSize): array {
                static::assertTrue($criteria->hasState(Criteria::STATE_ELASTICSEARCH_AWARE));
                static::assertEquals(new RangeFilter('autoIncrement', [RangeFilter::GT => $pageSize]), $criteria->getFilters()['increment']);

                return self::page([$allMatches[$pageSize]], $pageSize + 1);
            },
        ], $definition);

        $updatedIds = [];
        $manyToManyFieldUpdater = static::createStub(ManyToManyIdFieldUpdater::class);
        $manyToManyFieldUpdater
            ->method('update')
            ->willReturnCallback(static function (string $entity, array $ids) use (&$updatedIds): void {
                array_push($updatedIds, ...$ids);
            });

        $updater = new ProductStreamUpdater(
            $connection,
            $definition,
            $repository,
            static::createStub(MessageBusInterface::class),
            $manyToManyFieldUpdater,
            $this->createDefaultLanguageRepo(),
            true,
            parameterBag: new ParameterBag($elasticsearchEnabled === null ? [] : [
                'elasticsearch.enabled' => $elasticsearchEnabled,
                'elasticsearch.index_settings.max_result_window' => $maxResultWindow,
            ]),
        );

        $updater->handle($message);

        static::assertSame($allMatches, $updatedIds);
    }

    /**
     * @return iterable<string, array{?bool, ?int, int}>
     */
    public static function searchPageSizeProvider(): iterable
    {
        yield 'without OpenSearch the batch is capped at 50k' => [null, null, 50000];
        yield 'disabled OpenSearch does not restrict SQL batches' => [false, 500, 50000];
        yield 'small result windows are respected' => [true, 500, 500];
        yield 'the default OpenSearch window limits the batch to 10k' => [true, 10000, 10000];
        yield 'larger OpenSearch windows do not exceed the 50k cap' => [true, 100000, 50000];
    }

    public function testRejectsAnEmptyResultWindow(): void
    {
        $this->expectExceptionObject(ProductException::invalidStreamResultWindow(0));

        new ProductStreamUpdater(
            static::createStub(Connection::class),
            new ProductDefinition(),
            StaticEntityRepository::of(ProductCollection::class, []),
            static::createStub(MessageBusInterface::class),
            static::createStub(ManyToManyIdFieldUpdater::class),
            $this->createDefaultLanguageRepo(),
            indexingEnabled: true,
            parameterBag: new ParameterBag([
                'elasticsearch.enabled' => true,
                'elasticsearch.index_settings.max_result_window' => 0,
            ]),
        );
    }

    public function testUpdateProductsSkipsInvalidFilter(): void
    {
        $context = Context::createDefaultContext();

        $apiFilter = json_encode([[
            'type' => 'equals',
            'field' => 'active',
            'value' => '1',
        ]]);

        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('fetchAllAssociative')
            ->willReturn([['id' => Uuid::randomBytes(), 'api_filter' => $apiFilter]]);

        // the invalid filter is skipped, so the transaction still runs but inserts nothing
        $connection
            ->expects($this->once())
            ->method('transactional');

        $definition = new ProductDefinition();
        $repository = StaticEntityRepository::of(ProductCollection::class, [
            static function (): array {
                throw DataAbstractionLayerException::unmappedField('non-existing-field', new ProductDefinition());
            },
        ], $definition);

        $updater = new ProductStreamUpdater(
            $connection,
            $definition,
            $repository,
            static::createStub(MessageBusInterface::class),
            static::createStub(ManyToManyIdFieldUpdater::class),
            $this->createDefaultLanguageRepo(),
            true,
        );

        $updater->updateProducts([Uuid::randomHex()], $context);
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

    private static function firstPageCriteria(Criteria $criteria): Criteria
    {
        $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);
        $criteria->setLimit(50000);
        $criteria->addSorting(new FieldSorting('autoIncrement', FieldSorting::ASCENDING));
        $criteria->setFilter('increment', new RangeFilter('autoIncrement', [RangeFilter::GTE => 0]));
        $criteria->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_NONE);

        return $criteria;
    }

    /**
     * @param string[] $ids
     *
     * @return array<string, array{primaryKey: string, data: array{autoIncrement: int}}>
     */
    private static function page(array $ids, int $autoIncrement = 1): array
    {
        $page = [];
        foreach ($ids as $id) {
            $page[$id] = ['primaryKey' => $id, 'data' => ['autoIncrement' => $autoIncrement++]];
        }

        return $page;
    }

    /**
     * @return StaticEntityRepository<LanguageCollection>
     */
    private function createDefaultLanguageRepo(): StaticEntityRepository
    {
        $language = new LanguageEntity();
        $language->setId(Defaults::LANGUAGE_SYSTEM);

        $repo = new StaticEntityRepository([new LanguageCollection([$language])]);

        return $repo;
    }
}
