<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Profiling\DataAbstractionLayer;

use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\Middleware;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\SalesChannelApiSource;
use Shopware\Core\Framework\Api\Sync\SyncOperation;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEventFactory;
use Shopware\Core\Framework\DataAbstractionLayer\Read\EntityReaderInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\TermsAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntityAggregatorInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearcherInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\Filter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\VersionManager;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriterInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Profiling\DataAbstractionLayer\DalCollector;
use Shopware\Core\Profiling\DataAbstractionLayer\DalRequestLog;
use Shopware\Core\Profiling\DataAbstractionLayer\ProfilingEntityAggregator;
use Shopware\Core\Profiling\DataAbstractionLayer\ProfilingEntityReader;
use Shopware\Core\Profiling\DataAbstractionLayer\ProfilingEntitySearcher;
use Shopware\Core\Profiling\DataAbstractionLayer\ProfilingEntityWriter;
use Shopware\Core\Profiling\Doctrine\BacktraceDebugDataHolder;
use Shopware\Core\Profiling\Doctrine\ProfilingMiddleware;
use Shopware\Core\Test\Assert\Serialization;
use Symfony\Bridge\Doctrine\Middleware\Debug\Query;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\TraceableCommand;
use Symfony\Component\Console\Debug\CliRequest;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Stopwatch\Stopwatch;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(DalCollector::class)]
class DalCollectorTest extends TestCase
{
    private BacktraceDebugDataHolder $dataHolder;

    private DalCollector $collector;

    protected function setUp(): void
    {
        $this->dataHolder = new BacktraceDebugDataHolder(['default']);
        $this->collector = new DalCollector($this->connectionWith(new ProfilingMiddleware($this->dataHolder)));
    }

    public function testGroupsTheStepsOfARepositorySearchIntoOneCall(): void
    {
        $productA = Uuid::randomHex();
        $productB = Uuid::randomHex();

        $aggregator = static::createStub(EntityAggregatorInterface::class);
        $aggregator->method('aggregate')->willReturnCallback(function (): AggregationResultCollection {
            $this->dataHolder->addQuery('default', new Query('SELECT manufacturer_id FROM product'));

            return new AggregationResultCollection();
        });

        $searcher = static::createStub(EntitySearcherInterface::class);
        $searcher->method('search')->willReturnCallback(function (ProductDefinition $definition, Criteria $criteria, Context $context) use ($productA, $productB): IdSearchResult {
            $this->dataHolder->addQuery('default', new Query('SELECT id FROM product'));

            return new IdSearchResult(12, [
                $productA => ['primaryKey' => $productA, 'data' => ['id' => $productA]],
                $productB => ['primaryKey' => $productB, 'data' => ['id' => $productB]],
            ], $criteria, $context);
        });

        $reader = static::createStub(EntityReaderInterface::class);
        $reader->method('read')->willReturnCallback(function () use ($productA, $productB): ProductCollection {
            $this->dataHolder->addQuery('default', new Query('SELECT * FROM product'));
            $this->dataHolder->addQuery('default', new Query('SELECT * FROM product_manufacturer'));

            return new ProductCollection([$this->product($productA), $this->product($productB)]);
        });

        $criteria = (new Criteria())
            ->setLimit(2)
            ->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_EXACT)
            ->addFilter(new EqualsFilter('active', true))
            ->addAssociation('manufacturer')
            ->addAggregation(new TermsAggregation('manufacturers', 'manufacturerId'));

        $repository = $this->repository($reader, $searcher, $aggregator);
        $line = __LINE__ + 1;
        $repository->search($criteria, Context::createDefaultContext());

        $this->collector->lateCollect();

        static::assertSame(1, $this->collector->getCallCount());
        static::assertSame(3, $this->collector->getOperationCount());
        static::assertSame(['sql' => 4], $this->collector->getQueryCountsBySource());

        $call = $this->collector->getCalls()[0];
        static::assertSame('product', $call['entity']);
        static::assertSame(EntityRepository::class . '::search', $call['method']);
        static::assertSame(self::class . '::' . __FUNCTION__, $call['caller']);
        static::assertSame(__FILE__, $call['file']);
        static::assertSame($line, $call['line']);
        static::assertSame(['aggregate', 'searchIds', 'read'], array_column($call['operations'], 'operation'));
        static::assertSame(2, $call['count']);
        static::assertSame(12, $call['total']);
        static::assertSame('sql', $call['backend']);
        static::assertSame([0, 1, 2, 3], $call['queries']);
        static::assertSame([[0], [1], [2, 3]], array_column($call['operations'], 'queries'));
        static::assertSame([
            'filter' => [['type' => 'equals', 'field' => 'active', 'value' => true]],
            'aggregations' => ['manufacturers' => 'TermsAggregation(manufacturerId)'],
            'associations' => ['manufacturer' => []],
            'limit' => 2,
            'total-count-mode' => Criteria::TOTAL_COUNT_MODE_EXACT,
        ], $call['criteria']->getValue(true));
    }

    public function testFlagsRepeatedCallsWithTheSameCriteriaShape(): void
    {
        $reader = static::createStub(EntityReaderInterface::class);
        $reader->method('read')->willReturn(new ProductCollection());
        $repository = $this->repository($reader);
        $context = Context::createDefaultContext();

        $productA = Uuid::randomHex();
        $productB = Uuid::randomHex();

        $line = __LINE__ + 2;
        foreach ([$productA, $productB, $productA] as $productId) {
            $repository->search(new Criteria([$productId]), $context);
        }

        $repository->search((new Criteria([$productA]))->addAssociation('cover'), $context);

        $this->collector->lateCollect();
        $collector = Serialization::assertRoundTrip($this->collector);

        static::assertSame(4, $collector->getCallCount());

        $repeated = $collector->getRepeatedCalls();
        static::assertCount(1, $repeated);
        static::assertSame('product', $repeated[0]['entity']);
        static::assertSame(EntityRepository::class . '::search', $repeated[0]['method']);
        static::assertSame(3, $repeated[0]['count']);
        static::assertSame(1, $repeated[0]['duplicates']);
        static::assertSame([0, 1, 2], $repeated[0]['calls']);
        static::assertSame([['call' => self::class . '::' . __FUNCTION__, 'file' => __FILE__, 'line' => $line]], $repeated[0]['callers']);
        static::assertSame([true, true, true, false], array_column($collector->getCalls(), 'repeated'));
    }

    public function testAttributesQueriesToTheInnermostCall(): void
    {
        $innerSearcher = static::createStub(EntitySearcherInterface::class);
        $innerSearcher->method('search')->willReturnCallback(function (ProductDefinition $definition, Criteria $criteria, Context $context): IdSearchResult {
            $this->dataHolder->addQuery('default', new Query('SELECT id FROM product_media'));

            return new IdSearchResult(0, [], $criteria, $context);
        });
        $innerSearcher = new ProfilingEntitySearcher($innerSearcher, $this->collector);

        $reader = static::createStub(EntityReaderInterface::class);
        $reader->method('read')->willReturnCallback(function (ProductDefinition $definition, Criteria $criteria, Context $context) use ($innerSearcher): ProductCollection {
            $this->dataHolder->addQuery('default', new Query('SELECT * FROM product'));
            $innerSearcher->search($definition, new Criteria(), $context);
            $this->dataHolder->addQuery('default', new Query('SELECT * FROM product_translation'));

            return new ProductCollection();
        });

        (new ProfilingEntityReader($reader, $this->collector))->read(new ProductDefinition(), new Criteria(), Context::createDefaultContext());

        $this->collector->lateCollect();
        $calls = $this->collector->getCalls();

        static::assertSame([0, 1], array_column($calls, 'depth'));
        static::assertSame([[0, 2], [1]], array_column($calls, 'queries'));
        static::assertSame(['sql' => 3], $this->collector->getQueryCountsBySource());
    }

    public function testAttributesSearchRequestsToTheInnermostCall(): void
    {
        $requestLog = new FakeRequestLog();
        $collector = new DalCollector($this->connectionWith(new ProfilingMiddleware($this->dataHolder)), [$requestLog]);

        $innerSearcher = static::createStub(EntitySearcherInterface::class);
        $innerSearcher->method('search')->willReturnCallback(static function (ProductDefinition $definition, Criteria $criteria, Context $context) use ($requestLog): IdSearchResult {
            $requestLog->send('storefront', 'http://opensearch/sw_product/_search', ['query' => ['match_all' => []]]);

            return new IdSearchResult(0, [], $criteria, $context);
        });
        $innerSearcher = new ProfilingEntitySearcher($innerSearcher, $collector);

        $reader = static::createStub(EntityReaderInterface::class);
        $reader->method('read')->willReturnCallback(static function (ProductDefinition $definition, Criteria $criteria, Context $context) use ($requestLog, $innerSearcher): ProductCollection {
            $requestLog->send('admin', 'http://opensearch/sw-admin-product-listing/_search', ['size' => 10]);
            $innerSearcher->search($definition, new Criteria(), $context);
            $requestLog->send('storefront', 'http://opensearch/sw_category/_search', ['size' => 1]);

            return new ProductCollection();
        });

        (new ProfilingEntityReader($reader, $collector))->read(new ProductDefinition(), new Criteria(), Context::createDefaultContext());

        $collector->lateCollect();
        [$outer, $inner] = $collector->getCalls();
        $outerRequests = $outer['operations'][0]['requests'];
        $innerRequests = $inner['operations'][0]['requests'];

        static::assertSame(
            ['http://opensearch/sw_category/_search', 'http://opensearch/sw-admin-product-listing/_search'],
            array_column($outerRequests, 'url')
        );
        static::assertSame(['http://opensearch/sw_product/_search'], array_column($innerRequests, 'url'));
        static::assertSame('fake', $innerRequests[0]['source']);
        static::assertSame(1.5, $innerRequests[0]['time']);
        static::assertSame(0, $innerRequests[0]['position']);
        static::assertSame(['query' => ['match_all' => []]], $innerRequests[0]['body']->getValue(true));
        static::assertSame([2, 1], array_column($collector->getCalls(), 'queryCount'));
        static::assertSame(['sql' => 0, 'fake' => 3], $collector->getQueryCountsBySource());
    }

    public function testSummarizesLongListsAndUnsupportedFilters(): void
    {
        $ids = array_map(static fn (): string => Uuid::randomHex(), range(1, 12));

        $criteria = (new Criteria($ids))
            ->setTitle('product-detail::load')
            ->addFilter(new UnsupportedFilter())
            ->addFields(['name']);

        $reader = static::createStub(EntityReaderInterface::class);
        $reader->method('read')->willReturn(new ProductCollection());
        (new ProfilingEntityReader($reader, $this->collector))->read(new ProductDefinition(), $criteria, Context::createDefaultContext());

        $this->collector->lateCollect();

        static::assertSame([
            'title' => 'product-detail::load',
            'ids' => [...\array_slice($ids, 0, 10), '… 2 more'],
            'filter' => [['type' => 'UnsupportedFilter', 'fields' => ['customFields.size']]],
            'fields' => ['name'],
        ], $this->collector->getCalls()[0]['criteria']->getValue(true));
    }

    public function testDescribesTheContext(): void
    {
        $salesChannelId = Uuid::randomHex();
        $languageId = Uuid::randomHex();
        $versionId = Uuid::randomHex();
        $context = new Context(
            new SalesChannelApiSource($salesChannelId),
            languageIdChain: [$languageId, Defaults::LANGUAGE_SYSTEM],
            versionId: $versionId,
            considerInheritance: true,
        );

        $reader = static::createStub(EntityReaderInterface::class);
        $reader->method('read')->willReturn(new ProductCollection());
        $reader = new ProfilingEntityReader($reader, $this->collector);

        $context->scope(Context::CRUD_API_SCOPE, static fn () => $reader->read(new ProductDefinition(), new Criteria(), $context));

        $this->collector->lateCollect();

        static::assertSame([
            'languages' => [$languageId, Defaults::LANGUAGE_SYSTEM],
            'version' => $versionId,
            'scope' => Context::CRUD_API_SCOPE,
            'source' => 'SalesChannelApiSource',
            'salesChannel' => $salesChannelId,
            'inheritance' => true,
        ], $this->collector->getCalls()[0]['context']);
    }

    public function testMarksResultsServedByElasticsearch(): void
    {
        $searcher = static::createStub(EntitySearcherInterface::class);
        $searcher->method('search')->willReturnCallback(static function (ProductDefinition $definition, Criteria $criteria, Context $context): IdSearchResult {
            $result = new IdSearchResult(0, [], $criteria, $context);
            $result->addState('loaded-by-elastic');

            return $result;
        });

        (new ProfilingEntitySearcher($searcher, $this->collector))->search(new ProductDefinition(), new Criteria(), Context::createDefaultContext());

        $this->collector->lateCollect();

        static::assertSame('elasticsearch', $this->collector->getCalls()[0]['backend']);
    }

    public function testRecordsAWriteWithItsPayloadAndAffectedRows(): void
    {
        $productA = Uuid::randomHex();
        $productB = Uuid::randomHex();

        $inner = static::createStub(EntityWriterInterface::class);
        $inner->method('upsert')->willReturnCallback(function () use ($productA, $productB): array {
            $this->dataHolder->addQuery('default', new Query('INSERT INTO product'));
            $this->dataHolder->addQuery('default', new Query('INSERT INTO product_translation'));

            return [
                'product' => [$this->writeResult($productA, 'product'), $this->writeResult($productB, 'product')],
                'product_translation' => [$this->writeResult($productA, 'product_translation'), $this->writeResult($productB, 'product_translation')],
            ];
        });
        $writer = new ProfilingEntityWriter($inner, $this->collector);

        $line = __LINE__ + 1;
        $writer->upsert(new ProductDefinition(), [['id' => $productA, 'name' => 'A'], ['id' => $productB, 'stock' => 5]], WriteContext::createFromContext(Context::createDefaultContext()));

        $this->collector->lateCollect();
        $call = $this->collector->getCalls()[0];

        static::assertSame('product', $call['entity']);
        static::assertSame(EntityWriterInterface::class . '::upsert', $call['method']);
        static::assertSame(__FILE__, $call['file']);
        static::assertSame($line, $call['line']);
        static::assertTrue($call['write']);
        static::assertSame(2, $call['count']);
        static::assertSame(['product' => 2, 'product_translation' => 2], $call['affected']);
        static::assertSame([0, 1], $call['queries']);
        static::assertSame(['rows' => 2, 'fields' => ['id', 'name', 'stock']], $call['criteria']->getValue(true));
    }

    public function testRecordsACallThatThrowsAndRethrows(): void
    {
        $failure = new \RuntimeException('Invalid payload');
        $inner = static::createStub(EntityWriterInterface::class);
        $inner->method('upsert')->willReturnCallback(function () use ($failure): never {
            $this->dataHolder->addQuery('default', new Query('INSERT INTO product'));

            throw $failure;
        });
        $writer = new ProfilingEntityWriter($inner, $this->collector);

        $reader = static::createStub(EntityReaderInterface::class);
        $reader->method('read')->willReturnCallback(function (): ProductCollection {
            $this->dataHolder->addQuery('default', new Query('SELECT * FROM product'));

            return new ProductCollection();
        });
        $reader = new ProfilingEntityReader($reader, $this->collector);

        try {
            $writer->upsert(new ProductDefinition(), [['id' => Uuid::randomHex()]], WriteContext::createFromContext(Context::createDefaultContext()));
            static::fail('The exception was not rethrown');
        } catch (\RuntimeException $exception) {
            static::assertSame($failure, $exception);
        }
        $reader->read(new ProductDefinition(), new Criteria(), Context::createDefaultContext());

        $this->collector->lateCollect();
        $calls = $this->collector->getCalls();

        static::assertCount(2, $calls);
        static::assertSame(\RuntimeException::class, $calls[0]['error']);
        static::assertNull($calls[0]['count']);
        static::assertSame([0], $calls[0]['queries']);
        static::assertNull($calls[1]['error']);
        static::assertSame(0, $calls[1]['depth']);
        static::assertSame([1], $calls[1]['queries']);
    }

    public function testFlagsWritesRepeatedFromTheSameLine(): void
    {
        $inner = static::createStub(EntityWriterInterface::class);
        $inner->method('update')->willReturn([]);
        $writer = new ProfilingEntityWriter($inner, $this->collector);
        $writeContext = WriteContext::createFromContext(Context::createDefaultContext());

        $line = __LINE__ + 2;
        foreach ([1, 2, 1] as $stock) {
            $writer->update(new ProductDefinition(), [['id' => 'p1', 'stock' => $stock]], $writeContext);
        }

        $writer->update(new ProductDefinition(), [['id' => 'p1', 'stock' => 1]], $writeContext);

        $this->collector->lateCollect();
        $repeated = $this->collector->getRepeatedCalls();

        static::assertCount(1, $repeated);
        static::assertTrue($repeated[0]['write']);
        static::assertSame(3, $repeated[0]['count']);
        static::assertSame(1, $repeated[0]['duplicates']);
        static::assertSame($line, $repeated[0]['callers'][0]['line']);
        static::assertSame([true, true, true, false], array_column($this->collector->getCalls(), 'repeated'));
    }

    public function testRecordsASyncRequestAsOneCall(): void
    {
        $inner = static::createStub(EntityWriterInterface::class);
        $inner->method('sync')->willReturn(new WriteResult(
            ['category' => [$this->writeResult('c1', 'category')]],
            [],
            ['product' => [$this->writeResult('p1', 'product'), $this->writeResult('p2', 'product')]],
        ));

        (new ProfilingEntityWriter($inner, $this->collector))->sync([
            new SyncOperation('write-products', 'product', SyncOperation::ACTION_UPSERT, [['id' => 'p1'], ['id' => 'p2']]),
            new SyncOperation('delete-categories', 'category', SyncOperation::ACTION_DELETE, [['id' => 'c1']]),
        ], WriteContext::createFromContext(Context::createDefaultContext()));

        $this->collector->lateCollect();
        $call = $this->collector->getCalls()[0];

        static::assertSame('product, category', $call['entity']);
        static::assertSame([DalCollector::OPERATION_SYNC], array_column($call['operations'], 'operation'));
        static::assertSame(3, $call['count']);
        static::assertSame(['product' => 2, 'category' => 1], $call['affected']);
        static::assertSame(['operations' => ['upsert product (2 rows)', 'delete category (1 rows)']], $call['criteria']->getValue(true));
    }

    public function testFlagsSlowCalls(): void
    {
        $reader = static::createStub(EntityReaderInterface::class);
        $reader->method('read')->willReturnCallback(static function (): ProductCollection {
            usleep(110_000);

            return new ProductCollection();
        });

        (new ProfilingEntityReader($reader, $this->collector))->read(new ProductDefinition(), new Criteria(), Context::createDefaultContext());

        $this->collector->lateCollect();

        static::assertSame([DalCollector::WARNING_SLOW], $this->collector->getCalls()[0]['warnings']);
        static::assertSame([DalCollector::WARNING_SLOW => 1], $this->collector->getWarningCounts());
    }

    public function testFlagsLargeReadsWithoutALimit(): void
    {
        $products = new ProductCollection(array_map(fn (): ProductEntity => $this->product(Uuid::randomHex()), range(1, 200)));

        $reader = static::createStub(EntityReaderInterface::class);
        $reader->method('read')->willReturn($products);
        $reader = new ProfilingEntityReader($reader, $this->collector);

        $reader->read(new ProductDefinition(), new Criteria(), Context::createDefaultContext());
        $reader->read(new ProductDefinition(), (new Criteria())->setLimit(500), Context::createDefaultContext());

        $this->collector->lateCollect();

        static::assertSame([[DalCollector::WARNING_LARGE_READ], []], array_column($this->collector->getCalls(), 'warnings'));
    }

    #[DataProvider('requestProvider')]
    public function testFlagsWritesOnlyDuringSafeWebRequests(Request $request, bool $flagged): void
    {
        $inner = static::createStub(EntityWriterInterface::class);
        $inner->method('upsert')->willReturn([]);

        (new ProfilingEntityWriter($inner, $this->collector))->upsert(new ProductDefinition(), [['id' => 'p1']], WriteContext::createFromContext(Context::createDefaultContext()));

        $this->collector->collect($request, new Response());
        $this->collector->lateCollect();

        static::assertSame($flagged ? [DalCollector::WARNING_WRITE_IN_SAFE_REQUEST] : [], $this->collector->getCalls()[0]['warnings']);
    }

    public static function requestProvider(): \Generator
    {
        yield 'a write during a GET request is flagged' => [Request::create('/'), true];
        yield 'a write during a POST request is expected' => [Request::create('/', 'POST'), false];
        yield 'a write in a profiled console command is expected' => [new CliRequest(new TraceableCommand(new Command('dal:test'), new Stopwatch())), false];
    }

    public function testRecordsNothingWithoutSqlProfiling(): void
    {
        $collector = new DalCollector($this->connectionWith());
        $collection = new ProductCollection();

        $result = $collector->traceRead(DalCollector::OPERATION_READ, new ProductDefinition(), new Criteria(), Context::createDefaultContext(), static fn () => $collection);
        $collector->lateCollect();

        static::assertSame($collection, $result);
        static::assertFalse($collector->isEnabled());
        static::assertSame([], $collector->getCalls());
    }

    public function testStopsRecordingAfterTwoThousandOperations(): void
    {
        $reader = static::createStub(EntityReaderInterface::class);
        $reader->method('read')->willReturn(new ProductCollection());
        $reader = new ProfilingEntityReader($reader, $this->collector);

        for ($i = 0; $i < 2001; ++$i) {
            $reader->read(new ProductDefinition(), new Criteria(), Context::createDefaultContext());
        }

        $this->collector->lateCollect();

        static::assertSame(2000, $this->collector->getOperationCount());
        static::assertSame(1, $this->collector->getSkippedCount());
    }

    public function testResetForgetsRecordedCalls(): void
    {
        $reader = static::createStub(EntityReaderInterface::class);
        $reader->method('read')->willReturn(new ProductCollection());
        (new ProfilingEntityReader($reader, $this->collector))->read(new ProductDefinition(), new Criteria(), Context::createDefaultContext());

        $this->collector->reset();
        $this->collector->lateCollect();

        static::assertTrue($this->collector->isEnabled());
        static::assertSame(0, $this->collector->getCallCount());
        static::assertSame(0.0, $this->collector->getTime());
    }

    private function connectionWith(Middleware ...$middlewares): Connection
    {
        $configuration = new Configuration();
        $configuration->setMiddlewares($middlewares);

        $connection = static::createStub(Connection::class);
        $connection->method('getConfiguration')->willReturn($configuration);

        return $connection;
    }

    /**
     * @return EntityRepository<ProductCollection>
     */
    private function repository(
        EntityReaderInterface $reader,
        ?EntitySearcherInterface $searcher = null,
        ?EntityAggregatorInterface $aggregator = null,
    ): EntityRepository {
        return new EntityRepository(
            new ProductDefinition(),
            new ProfilingEntityReader($reader, $this->collector),
            static::createStub(VersionManager::class),
            new ProfilingEntitySearcher($searcher ?? static::createStub(EntitySearcherInterface::class), $this->collector),
            new ProfilingEntityAggregator($aggregator ?? static::createStub(EntityAggregatorInterface::class), $this->collector),
            new EventDispatcher(),
            static::createStub(EntityLoadedEventFactory::class),
        );
    }

    private function writeResult(string $id, string $entity): EntityWriteResult
    {
        return new EntityWriteResult($id, ['id' => $id], $entity, EntityWriteResult::OPERATION_INSERT);
    }

    private function product(string $id): ProductEntity
    {
        $product = new ProductEntity();
        $product->setId($id);

        return $product;
    }
}

/**
 * @internal
 */
class FakeRequestLog implements DalRequestLog
{
    /**
     * @var array<string, list<array{url: string, time: float, body: mixed, position: int}>>
     */
    private array $requests = ['storefront' => [], 'admin' => []];

    public function send(string $channel, string $url, mixed $body): void
    {
        $this->requests[$channel][] = ['url' => $url, 'time' => 1.5, 'body' => $body, 'position' => \count($this->requests[$channel])];
    }

    public function getName(): string
    {
        return 'fake';
    }

    public function getRequestCounts(): array
    {
        return array_map(\count(...), $this->requests);
    }

    public function getRequest(string $channel, int $index): array
    {
        return $this->requests[$channel][$index];
    }
}

/**
 * @internal
 */
class UnsupportedFilter extends Filter
{
    public function getFields(): array
    {
        return ['customFields.size'];
    }
}
