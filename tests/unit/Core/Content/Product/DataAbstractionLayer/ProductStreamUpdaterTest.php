<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\DataAbstractionLayer;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\Stub;
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
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\Event\NestedEventCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Language\LanguageCollection;
use Shopware\Core\System\Language\LanguageEntity;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Elasticsearch\Framework\DataAbstractionLayer\ElasticsearchEntitySearcher;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ProductStreamUpdater::class)]
class ProductStreamUpdaterTest extends TestCase
{
    private const ACTIVE_FILTER = '[{"type":"equals","field":"active","value":"1"}]';

    /**
     * @var list<string>
     */
    private array $added = [];

    /**
     * @var list<string>
     */
    private array $deleted = [];

    /**
     * @var list<string>
     */
    private array $streamIdsUpdated = [];

    /**
     * @var list<int>
     */
    private array $searchLimits = [];

    private int $largestWrite = 0;

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
        $repository = StaticEntityRepository::of(ProductCollection::class, [$this->searchMatches([])], new ProductDefinition());
        $connection = $this->createConnection((string) current(array_column($filters, 'api_filter')), []);

        $this->createUpdater($connection, $repository)->handle(self::message());

        $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);
        $criteria->setLimit(1000);
        $criteria->addSorting(new FieldSorting('autoIncrement'));
        $criteria->addFilter(new RangeFilter('autoIncrement', [RangeFilter::GT => 0]));

        static::assertEquals([$criteria], $repository->searchedCriteria);
    }

    /**
     * @param list<int> $mapped
     * @param list<int> $matching
     * @param list<int> $added
     * @param list<int> $deleted
     */
    #[DataProvider('differenceProvider')]
    public function testHandleWritesTheDifference(array $mapped, array $matching, array $added, array $deleted): void
    {
        $repository = StaticEntityRepository::of(ProductCollection::class, [
            $this->searchMatches([Defaults::LANGUAGE_SYSTEM => self::products($matching)]),
        ], new ProductDefinition());

        $this->createUpdater($this->createConnection(self::ACTIVE_FILTER, self::products($mapped)), $repository)->handle(self::message());

        static::assertEqualsCanonicalizing(array_values(self::products($added)), $this->added);
        static::assertEqualsCanonicalizing(array_values(self::products($deleted)), $this->deleted);
        static::assertEqualsCanonicalizing([...$this->added, ...$this->deleted], $this->streamIdsUpdated);
    }

    public function testHandleWalksAStreamLargerThanOnePage(): void
    {
        $repository = StaticEntityRepository::of(ProductCollection::class, array_fill(0, 4, $this->searchMatches([
            Defaults::LANGUAGE_SYSTEM => self::products(range(10000, 54999)),
        ])), new ProductDefinition());

        $this->createUpdater($this->createConnection(self::ACTIVE_FILTER, self::products(range(0, 29999))), $repository)->handle(self::message());

        static::assertSame([1000, 20000, 20000, 20000], $this->searchLimits);
        static::assertLessThanOrEqual(250, $this->largestWrite);
        self::assertSameProducts(range(30000, 54999), $this->added);
        self::assertSameProducts(range(0, 9999), $this->deleted);
        self::assertSameProducts([...range(0, 9999), ...range(30000, 54999)], $this->streamIdsUpdated);
    }

    public function testHandleKeepsProductsThatMatchInAnyLanguage(): void
    {
        $germanId = Uuid::randomHex();
        $english = range(0, 99999, 2);
        $german = range(0, 99999, 3);
        $mapped = range(0, 119999, 5);
        $repository = StaticEntityRepository::of(ProductCollection::class, array_fill(0, 10, $this->searchMatches([
            Defaults::LANGUAGE_SYSTEM => self::products($english),
            $germanId => self::products($german),
        ])), new ProductDefinition());

        $this->createUpdater($this->createConnection(self::ACTIVE_FILTER, self::products($mapped)), $repository, Defaults::LANGUAGE_SYSTEM, $germanId)
            ->handle(self::message());

        $matching = array_unique([...$english, ...$german]);
        self::assertSameProducts(array_values(array_diff($matching, $mapped)), $this->added);
        self::assertSameProducts(array_values(array_diff($mapped, $matching)), $this->deleted);
    }

    public function testHandleKeepsOpenSearchPagesSmall(): void
    {
        $repository = StaticEntityRepository::of(ProductCollection::class, array_fill(0, 4, $this->searchMatches([
            Defaults::LANGUAGE_SYSTEM => self::products(range(0, 3499)),
        ], fromElasticsearch: true)), new ProductDefinition());

        $this->createUpdater($this->createConnection(self::ACTIVE_FILTER, []), $repository)->handle(self::message());

        static::assertSame([1000, 1000, 1000, 1000], $this->searchLimits);
        self::assertSameProducts(range(0, 3499), $this->added);
    }

    public function testInvalidFilter(): void
    {
        $repository = StaticEntityRepository::of(ProductCollection::class, [
            static fn () => throw DataAbstractionLayerException::unmappedField('non-existing-field', new ProductDefinition()),
        ], new ProductDefinition());

        $this->createUpdater($this->createConnection(self::ACTIVE_FILTER, self::products([1, 2])), $repository)->handle(self::message());

        static::assertSame([], $this->added);
        static::assertEqualsCanonicalizing(array_values(self::products([1, 2])), $this->deleted);
        static::assertEqualsCanonicalizing($this->deleted, $this->streamIdsUpdated);
    }

    public function testInvalidStreamRemovesEveryMappingInBoundedWrites(): void
    {
        $repository = StaticEntityRepository::of(ProductCollection::class, [], new ProductDefinition());

        $this->createUpdater($this->createConnection(null, self::products(range(0, 24999)), invalid: 1), $repository)->handle(self::message());

        static::assertSame([], $repository->searchedCriteria);
        static::assertLessThanOrEqual(250, $this->largestWrite);
        self::assertSameProducts(range(0, 24999), $this->deleted);
    }

    public function testHandleNeedsTheAutoIncrementOfEveryMatch(): void
    {
        $productId = Uuid::randomHex();
        $repository = StaticEntityRepository::of(ProductCollection::class, [[$productId]], new ProductDefinition());

        $this->expectExceptionObject(ProductException::streamMatchWithoutAutoIncrement($productId));

        $this->createUpdater($this->createConnection(self::ACTIVE_FILTER, []), $repository)->handle(self::message());
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
     * @return iterable<string, array{mapped: list<int>, matching: list<int>, added: list<int>, deleted: list<int>}>
     */
    public static function differenceProvider(): iterable
    {
        yield 'a stream without matches and mappings writes nothing' => ['mapped' => [], 'matching' => [], 'added' => [], 'deleted' => []];

        yield 'new matches are mapped' => ['mapped' => [], 'matching' => [3, 4, 5], 'added' => [3, 4, 5], 'deleted' => []];

        yield 'mappings without a match are removed' => ['mapped' => [1, 2], 'matching' => [], 'added' => [], 'deleted' => [1, 2]];

        yield 'unchanged matches write nothing' => ['mapped' => [1, 2], 'matching' => [1, 2], 'added' => [], 'deleted' => []];

        yield 'only the difference is written' => ['mapped' => [1, 2], 'matching' => [2, 3], 'added' => [3], 'deleted' => [1]];

        yield 'a complete change replaces every mapping' => ['mapped' => [1, 2], 'matching' => [3, 4, 5], 'added' => [3, 4, 5], 'deleted' => [1, 2]];
    }

    /**
     * @param list<int> $expected
     * @param list<string> $actual
     */
    private static function assertSameProducts(array $expected, array $actual): void
    {
        $expected = array_values(self::products($expected));

        static::assertSame([], array_values(array_diff($expected, $actual)), 'Products are missing');
        static::assertSame([], array_values(array_diff($actual, $expected)), 'Products were not expected');
        static::assertCount(\count($expected), $actual, 'Products were written more than once');
    }

    private static function message(): ProductStreamMappingIndexingMessage
    {
        return new ProductStreamMappingIndexingMessage(Uuid::randomHex());
    }

    /**
     * @param list<int> $numbers
     *
     * @return array<int, string>
     */
    private static function products(array $numbers): array
    {
        sort($numbers);

        $products = [];
        foreach ($numbers as $number) {
            $products[10 + 3 * $number] = Uuid::fromStringToHex('product-' . $number);
        }

        return $products;
    }

    /**
     * @param array<int, string> $mappings
     */
    private function createConnection(?string $apiFilter, array $mappings, int $invalid = 0): Connection&Stub
    {
        $connection = static::createStub(Connection::class);
        $connection->method('fetchAssociative')->willReturn(['invalid' => $invalid, 'api_filter' => $apiFilter]);
        $connection->method('fetchAllNumeric')->willReturnCallback(static function (string $sql, array $params) use ($mappings): array {
            $rows = [];
            foreach ($mappings as $autoIncrement => $id) {
                if ($autoIncrement > $params['after'] && \count($rows) < $params['limit']) {
                    $rows[] = [$autoIncrement, $id];
                }
            }

            return $rows;
        });
        $connection->method('transactional')->willReturnCallback(static fn (\Closure $closure) => $closure($connection));
        $connection->method('executeStatement')->willReturnCallback(function (string $sql, array $params): int {
            /** @var list<string> $bytes */
            $bytes = $params['ids'];
            $ids = Uuid::fromBytesToHexList($bytes);
            if (str_starts_with($sql, 'DELETE')) {
                $this->deleted = [...$this->deleted, ...$ids];
            } else {
                $this->added = [...$this->added, ...$ids];
            }
            $this->largestWrite = max($this->largestWrite, \count($ids));

            return \count($ids);
        });

        return $connection;
    }

    /**
     * @param array<string, array<int, string>> $matchesByLanguage
     *
     * @return \Closure(Criteria, Context): IdSearchResult
     */
    private function searchMatches(array $matchesByLanguage, bool $fromElasticsearch = false): \Closure
    {
        return function (Criteria $criteria, Context $context) use ($matchesByLanguage, $fromElasticsearch): IdSearchResult {
            $limit = $criteria->getLimit();
            static::assertNotNull($limit, 'The stream is searched as a whole instead of page by page');
            $this->searchLimits[] = $limit;

            $after = 0;
            foreach ($criteria->getFilters() as $filter) {
                if ($filter instanceof RangeFilter && $filter->getField() === 'autoIncrement') {
                    $after = (int) $filter->getParameter(RangeFilter::GT);
                }
            }

            $data = [];
            foreach ($matchesByLanguage[$context->getLanguageId()] ?? [] as $autoIncrement => $id) {
                if ($autoIncrement > $after && \count($data) < $limit) {
                    $data[$id] = ['primaryKey' => $id, 'data' => ['autoIncrement' => $fromElasticsearch ? (float) $autoIncrement : $autoIncrement]];
                }
            }

            $result = new IdSearchResult(\count($data), $data, $criteria, $context);
            if ($fromElasticsearch) {
                $result->addState(ElasticsearchEntitySearcher::RESULT_STATE);
            }

            return $result;
        };
    }

    /**
     * @param StaticEntityRepository<ProductCollection> $repository
     */
    private function createUpdater(Connection $connection, StaticEntityRepository $repository, string ...$languageIds): ProductStreamUpdater
    {
        $languages = new LanguageCollection();
        foreach ($languageIds ?: [Defaults::LANGUAGE_SYSTEM] as $languageId) {
            $language = new LanguageEntity();
            $language->setId($languageId);
            $languages->add($language);
        }

        $manyToManyIdFieldUpdater = static::createStub(ManyToManyIdFieldUpdater::class);
        $manyToManyIdFieldUpdater->method('update')->willReturnCallback(function (string $entity, array $ids, Context $context, ?string $propertyName): void {
            static::assertSame([ProductDefinition::ENTITY_NAME, 'streamIds'], [$entity, $propertyName]);
            foreach ($ids as $id) {
                static::assertIsString($id);
                $this->streamIdsUpdated[] = $id;
            }
        });

        return new ProductStreamUpdater(
            $connection,
            new ProductDefinition(),
            $repository,
            static::createStub(MessageBusInterface::class),
            $manyToManyIdFieldUpdater,
            new StaticEntityRepository([$languages]),
            true,
        );
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
