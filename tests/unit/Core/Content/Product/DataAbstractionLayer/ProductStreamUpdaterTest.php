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
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\Event\NestedEventCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Language\LanguageCollection;
use Shopware\Core\System\Language\LanguageEntity;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
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
     * @param list<string> $ids
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

        // no STATE_ELASTICSEARCH_AWARE: this path runs directly after the products have
        // been written, their Elasticsearch documents are not updated yet
        $criteria->addFilter(new EqualsAnyFilter('id', $ids));

        /** @var StaticEntityRepository<ProductCollection> */
        $repository = new StaticEntityRepository([
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
            static function (Criteria $actualCriteria, Context $context) use ($criteria, $newMatches): array {
                static::assertTrue($actualCriteria->hasState(Criteria::STATE_ELASTICSEARCH_AWARE));
                $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);

                static::assertEquals($criteria, $actualCriteria);

                return $newMatches;
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
            static function (Criteria $actualCriteria, Context $context) use ($criteria, $newMatches): array {
                static::assertEquals($criteria, $actualCriteria);

                return $newMatches;
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

    /**
     * Regression coverage for https://github.com/shopware/shopware/issues/10770.
     *
     * When a product stream contains many top-level AND conditions we must not
     * build a single giant criteria (which would explode past the MariaDB 61-table
     * join limit). Instead the updater chunks the conditions, runs multiple
     * `searchIds` calls and intersects the resulting id sets.
     */
    public function testLargeNumberOfConditionsIsChunkedIntoMultipleSearches(): void
    {
        $context = Context::createDefaultContext();

        $conditionCount = 70;
        $filters = [];
        for ($i = 0; $i < $conditionCount; ++$i) {
            $filters[] = [
                'type' => 'equals',
                'field' => 'active',
                'value' => '1',
            ];
        }

        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('fetchAllAssociative')
            ->willReturn([
                [
                    'id' => Uuid::randomHex(),
                    'api_filter' => json_encode($filters),
                ],
            ]);

        $candidateIds = [Uuid::randomHex(), Uuid::randomHex(), Uuid::randomHex()];

        $calls = [];
        /** @var StaticEntityRepository<ProductCollection> $repository */
        $repository = new StaticEntityRepository([
            static function (Criteria $actualCriteria) use (&$calls, $candidateIds): array {
                $calls[] = $actualCriteria;

                // Always return the candidate ids so that subsequent chunks keep matching.
                return $candidateIds;
            },
            static function (Criteria $actualCriteria) use (&$calls, $candidateIds): array {
                $calls[] = $actualCriteria;

                return $candidateIds;
            },
            static function (Criteria $actualCriteria) use (&$calls, $candidateIds): array {
                $calls[] = $actualCriteria;

                return $candidateIds;
            },
            static function (Criteria $actualCriteria) use (&$calls, $candidateIds): array {
                $calls[] = $actualCriteria;

                return $candidateIds;
            },
        ]);

        $updater = new ProductStreamUpdater(
            $connection,
            new ProductDefinition(),
            $repository,
            static::createStub(MessageBusInterface::class),
            static::createStub(ManyToManyIdFieldUpdater::class),
            $this->createDefaultLanguageRepo(),
            true
        );

        $updater->updateProducts($candidateIds, $context);

        // 70 conditions with a chunk size of 20 → 4 batches (20 + 20 + 20 + 10),
        // executed once for the single (system) language context.
        static::assertCount(4, $calls);

        foreach ($calls as $i => $criteria) {
            $criteriaFilters = $criteria->getFilters();
            // Every batch carries the id restriction (EqualsAnyFilter on id).
            $hasIdFilter = false;
            foreach ($criteriaFilters as $filter) {
                if ($filter instanceof EqualsAnyFilter && $filter->getField() === 'id') {
                    $hasIdFilter = true;

                    break;
                }
            }
            static::assertTrue($hasIdFilter, 'Chunk #' . $i . ' must be constrained to the candidate ids.');

            // Each chunk must carry at most CONDITION_CHUNK_SIZE condition filters
            // (plus the id filter) — which proves we never build a single giant criteria.
            static::assertLessThanOrEqual(21, \count($criteriaFilters));
        }
    }

    /**
     * Regression coverage for https://github.com/shopware/shopware/issues/10770.
     *
     * The Administration always wraps the conditions of a stream into a root OR
     * container (see `product-stream-condition.service.js::getOrContainerData`),
     * so the conditions to be chunked are never on the top level of `api_filter`.
     */
    public function testConditionsInsideTheAdministrationOrContainerAreChunked(): void
    {
        $context = Context::createDefaultContext();

        $conditions = [];
        for ($i = 0; $i < 70; ++$i) {
            $conditions[] = [
                'type' => 'equals',
                'field' => 'active',
                'value' => '1',
            ];
        }

        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('fetchAllAssociative')
            ->willReturn([
                [
                    'id' => Uuid::randomHex(),
                    'api_filter' => json_encode([[
                        'type' => 'multi',
                        'operator' => 'OR',
                        'queries' => [[
                            'type' => 'multi',
                            'operator' => 'AND',
                            'queries' => $conditions,
                        ]],
                    ]]),
                ],
            ]);

        $candidateIds = [Uuid::randomHex()];

        $calls = 0;
        $searches = [];
        for ($i = 0; $i < 4; ++$i) {
            $searches[] = static function (Criteria $criteria) use (&$calls, $candidateIds): array {
                ++$calls;

                return $candidateIds;
            };
        }

        // the repository compiles the definition, which is required to resolve the association paths
        $definition = new ProductDefinition();
        /** @var StaticEntityRepository<ProductCollection> $repository */
        $repository = new StaticEntityRepository($searches, $definition);

        $updater = new ProductStreamUpdater(
            $connection,
            $definition,
            $repository,
            static::createStub(MessageBusInterface::class),
            static::createStub(ManyToManyIdFieldUpdater::class),
            $this->createDefaultLanguageRepo(),
            true
        );

        $updater->updateProducts($candidateIds, $context);

        // 70 conditions with a chunk size of 20 → 4 batches (20 + 20 + 20 + 10)
        static::assertSame(4, $calls);
    }

    /**
     * Conditions addressing the same to-many association must not be spread over
     * separate top level filters: `JoinGroupBuilder` would then join the
     * association once per condition (as independent `EXISTS` sub queries)
     * instead of matching them against the same joined row.
     */
    public function testConditionsOnTheSameToManyAssociationStayInOneFilter(): void
    {
        $context = Context::createDefaultContext();

        $conditions = [
            ['type' => 'equals', 'field' => 'categories.name', 'value' => 'first'],
            ['type' => 'equals', 'field' => 'categories.name', 'value' => 'second'],
        ];
        for ($i = 0; $i < 40; ++$i) {
            $conditions[] = ['type' => 'equals', 'field' => 'active', 'value' => '1'];
        }

        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('fetchAllAssociative')
            ->willReturn([
                [
                    'id' => Uuid::randomHex(),
                    'api_filter' => json_encode([[
                        'type' => 'multi',
                        'operator' => 'AND',
                        'queries' => $conditions,
                    ]]),
                ],
            ]);

        $candidateIds = [Uuid::randomHex()];

        $grouped = null;
        $searches = [];
        for ($i = 0; $i < 3; ++$i) {
            $searches[] = static function (Criteria $criteria) use (&$grouped, $candidateIds): array {
                foreach ($criteria->getFilters() as $filter) {
                    if ($filter instanceof MultiFilter && $filter->getOperator() === MultiFilter::CONNECTION_AND) {
                        $grouped = $filter;
                    }
                }

                return $candidateIds;
            };
        }

        // the repository compiles the definition, which is required to resolve the association paths
        $definition = new ProductDefinition();
        /** @var StaticEntityRepository<ProductCollection> $repository */
        $repository = new StaticEntityRepository($searches, $definition);

        $updater = new ProductStreamUpdater(
            $connection,
            $definition,
            $repository,
            static::createStub(MessageBusInterface::class),
            static::createStub(ManyToManyIdFieldUpdater::class),
            $this->createDefaultLanguageRepo(),
            true
        );

        $updater->updateProducts($candidateIds, $context);

        static::assertInstanceOf(MultiFilter::class, $grouped);
        static::assertEquals(
            [
                new EqualsFilter('product.categories.name', 'first'),
                new EqualsFilter('product.categories.name', 'second'),
            ],
            $grouped->getQueries()
        );
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
        /** @var StaticEntityRepository<ProductCollection> */
        $repository = new StaticEntityRepository([
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
            // Top-level AND wrappers are flattened before the criteria is
            // executed, so that large conjunctions can be chunked without
            // exceeding the MariaDB 61-table join limit (see #10770).
            (new Criteria())->addFilter(
                new MultiFilter(MultiFilter::CONNECTION_OR, [
                    new RangeFilter('product.price', [RangeFilter::LTE => 50]),
                    new RangeFilter('product.prices.price', [RangeFilter::LTE => 50]),
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
            // Top-level AND wrappers are flattened before the criteria is
            // executed, so that large conjunctions can be chunked without
            // exceeding the MariaDB 61-table join limit (see #10770).
            (new Criteria())->addFilter(
                new MultiFilter(MultiFilter::CONNECTION_OR, [
                    new RangeFilter('product.price.percentage', [RangeFilter::LTE => 50]),
                    new RangeFilter('product.prices.price.percentage', [RangeFilter::LTE => 50]),
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
