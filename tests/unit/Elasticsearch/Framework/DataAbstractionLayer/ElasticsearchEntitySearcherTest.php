<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Elasticsearch\Framework\DataAbstractionLayer;

use OpenSearch\Client;
use OpenSearch\Exception\RuntimeException;
use OpenSearchDSL\Sort\FieldSort;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearcherInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Grouping\FieldGrouping;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\CustomField\CustomFieldService;
use Shopware\Core\Test\Stub\Framework\Adapter\Storage\ArrayKeyValueStorage;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Shopware\Elasticsearch\ElasticsearchException;
use Shopware\Elasticsearch\Framework\DataAbstractionLayer\AbstractElasticsearchSearchHydrator;
use Shopware\Elasticsearch\Framework\DataAbstractionLayer\CriteriaParser;
use Shopware\Elasticsearch\Framework\DataAbstractionLayer\ElasticsearchEntitySearcher;
use Shopware\Elasticsearch\Framework\DataAbstractionLayer\ElasticsearchEntitySearchHydrator;
use Shopware\Elasticsearch\Framework\DataAbstractionLayer\Event\ElasticsearchEntitySearcherSearchedEvent;
use Shopware\Elasticsearch\Framework\DataAbstractionLayer\Event\ElasticsearchEntitySearcherSearchEvent;
use Shopware\Elasticsearch\Framework\ElasticsearchHelper;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ElasticsearchEntitySearcher::class)]
class ElasticsearchEntitySearcherTest extends TestCase
{
    public function testEmptyQueryExceptionIsCatched(): void
    {
        $criteria = new Criteria();
        $criteria->setLimit(10);

        $client = $this->createMock(Client::class);
        // client should not be used if limit is 0
        $client->expects($this->never())
            ->method('search');

        $helper = static::createStub(ElasticsearchHelper::class);
        $helper
            ->method('allowSearch')
            ->willReturn(true);
        $helper
            ->method('addTerm')
            ->willThrowException(ElasticsearchException::emptyQuery());

        $searcher = new ElasticsearchEntitySearcher(
            $client,
            static::createStub(EntitySearcherInterface::class),
            $helper,
            static::createStub(CriteriaParser::class),
            static::createStub(AbstractElasticsearchSearchHydrator::class),
            new EventDispatcher(),
            '10s',
            'dfs_query_then_fetch'
        );

        $context = Context::createDefaultContext();

        $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);

        $result = $searcher->search(
            new ProductDefinition(),
            $criteria,
            $context
        );

        static::assertSame(0, $result->getTotal());
    }

    public function testWithCriteriaLimitOfZero(): void
    {
        $criteria = new Criteria();
        $criteria->setLimit(0);

        $client = $this->createMock(Client::class);
        // client should not be used if limit is 0
        $client->expects($this->never())
            ->method('search');

        $helper = static::createStub(ElasticsearchHelper::class);
        $helper
            ->method('allowSearch')
            ->willReturn(true);

        $searcher = new ElasticsearchEntitySearcher(
            $client,
            static::createStub(EntitySearcherInterface::class),
            $helper,
            static::createStub(CriteriaParser::class),
            static::createStub(AbstractElasticsearchSearchHydrator::class),
            new EventDispatcher(),
            '5s',
            'dfs_query_then_fetch'
        );

        $context = Context::createDefaultContext();

        $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);

        $result = $searcher->search(
            new ProductDefinition(),
            $criteria,
            $context
        );

        static::assertSame(0, $result->getTotal());
    }

    public function testSearchWithCount(): void
    {
        $criteria = new Criteria();
        $criteria->setLimit(10);

        $client = $this->createMock(Client::class);

        $client->expects($this->once())
            ->method('search')->with([
                'index' => '',
                'body' => [
                    'timeout' => '10s',
                    'from' => 0,
                    'size' => 10,
                    'track_total_hits' => true,
                ],
                'search_type' => 'dfs_query_then_fetch',
            ])->willReturn([]);

        $helper = static::createStub(ElasticsearchHelper::class);
        $helper
            ->method('allowSearch')
            ->willReturn(true);

        $searcher = new ElasticsearchEntitySearcher(
            $client,
            static::createStub(EntitySearcherInterface::class),
            $helper,
            static::createStub(CriteriaParser::class),
            static::createStub(AbstractElasticsearchSearchHydrator::class),
            new EventDispatcher(),
            '10s',
            'dfs_query_then_fetch'
        );

        $context = Context::createDefaultContext();

        $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);
        $criteria->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_EXACT);

        $searcher->search(
            new ProductDefinition(),
            $criteria,
            $context
        );
    }

    public function testSearchWithNoCount(): void
    {
        $criteria = new Criteria();
        $criteria->setLimit(10);
        $criteria->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_NONE);

        $client = $this->createMock(Client::class);

        $client->expects($this->once())
            ->method('search')->with([
                'index' => '',
                'body' => [
                    'timeout' => '10s',
                    'from' => 0,
                    'size' => 10,
                    'track_total_hits' => false,
                ],
                'search_type' => 'dfs_query_then_fetch',
            ])->willReturn([]);

        $helper = static::createStub(ElasticsearchHelper::class);
        $helper
            ->method('allowSearch')
            ->willReturn(true);

        $searcher = new ElasticsearchEntitySearcher(
            $client,
            static::createStub(EntitySearcherInterface::class),
            $helper,
            static::createStub(CriteriaParser::class),
            static::createStub(AbstractElasticsearchSearchHydrator::class),
            new EventDispatcher(),
            '10s',
            'dfs_query_then_fetch'
        );

        $context = Context::createDefaultContext();

        $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);

        $searcher->search(
            new ProductDefinition(),
            $criteria,
            $context
        );
    }

    public function testSearchWithExplainMode(): void
    {
        $criteria = new Criteria();
        $criteria->setLimit(10);
        $criteria->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_NONE);

        $client = $this->createMock(Client::class);

        $client->expects($this->once())
            ->method('search')->with([
                'index' => '',
                'include_named_queries_score' => true,
                'track_scores' => true,
                'body' => [
                    'timeout' => '10s',
                    'from' => 0,
                    'size' => 10,
                    'explain' => true,
                    'track_total_hits' => false,
                ],
                'search_type' => 'dfs_query_then_fetch',
            ])->willReturn([]);

        $helper = static::createStub(ElasticsearchHelper::class);
        $helper
            ->method('allowSearch')
            ->willReturn(true);

        $searcher = new ElasticsearchEntitySearcher(
            $client,
            static::createStub(EntitySearcherInterface::class),
            $helper,
            static::createStub(CriteriaParser::class),
            static::createStub(AbstractElasticsearchSearchHydrator::class),
            new EventDispatcher(),
            '10s',
            'dfs_query_then_fetch'
        );

        $context = Context::createDefaultContext();
        $context->addState(Context::ELASTICSEARCH_EXPLAIN_MODE);

        $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);

        $searcher->search(
            new ProductDefinition(),
            $criteria,
            $context
        );
    }

    public function testDispatchEvents(): void
    {
        $criteria = new Criteria();
        $criteria->setLimit(10);
        $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);

        $context = Context::createDefaultContext();

        $client = $this->createMock(Client::class);

        $client->expects($this->once())
            ->method('search')->with([
                'index' => '',
                'body' => [
                    'timeout' => '10s',
                    'from' => 0,
                    'size' => 10,
                    'track_total_hits' => false,
                ],
                'search_type' => 'dfs_query_then_fetch',
            ])->willReturn([
                'hits' => [
                    'hits' => [],
                ],
            ]);

        $helper = static::createStub(ElasticsearchHelper::class);
        $helper
            ->method('allowSearch')
            ->willReturn(true);

        $dispatcher = new EventDispatcher();
        $searchEventDispatched = false;
        $searchedEventDispatched = false;

        $dispatcher->addListener(ElasticsearchEntitySearcherSearchEvent::class, static function (ElasticsearchEntitySearcherSearchEvent $event) use (&$searchEventDispatched): void {
            $searchEventDispatched = true;
        });

        $dispatcher->addListener(ElasticsearchEntitySearcherSearchedEvent::class, static function (ElasticsearchEntitySearcherSearchedEvent $event) use (&$searchedEventDispatched): void {
            $searchedEventDispatched = true;
        });

        $searcher = new ElasticsearchEntitySearcher(
            $client,
            static::createStub(EntitySearcherInterface::class),
            $helper,
            static::createStub(CriteriaParser::class),
            static::createStub(AbstractElasticsearchSearchHydrator::class),
            $dispatcher,
            '10s',
            'dfs_query_then_fetch'
        );

        $searcher->search(
            new ProductDefinition(),
            $criteria,
            $context
        );

        static::assertTrue($searchEventDispatched);
        static::assertTrue($searchedEventDispatched);
    }

    public function testExceptionsGetLogged(): void
    {
        $criteria = new Criteria();
        $criteria->setLimit(1);

        $client = $this->createMock(Client::class);
        // client should not be used if limit is 0
        $client->expects($this->once())
            ->method('search')
            ->willThrowException(new RuntimeException());

        $helper = $this->createMock(ElasticsearchHelper::class);
        $helper->expects($this->once())->method('logAndThrowException');
        $helper->method('allowSearch')->willReturn(true);

        $searcher = new ElasticsearchEntitySearcher(
            $client,
            static::createStub(EntitySearcherInterface::class),
            $helper,
            new CriteriaParser(new EntityDefinitionQueryHelper(), static::createStub(CustomFieldService::class), new ArrayKeyValueStorage([])),
            static::createStub(AbstractElasticsearchSearchHydrator::class),
            new EventDispatcher(),
            '5s',
            'dfs_query_then_fetch'
        );

        $context = Context::createDefaultContext();
        $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);

        $result = $searcher->search(
            new ProductDefinition(),
            $criteria,
            $context
        );

        static::assertSame(0, $result->getTotal());
    }

    /**
     * @param list<string> $fields
     */
    #[DataProvider('groupingFieldsProvider')]
    public function testGroupingFieldsArePassedAsScriptParameters(array $fields, ?int $precisionThreshold): void
    {
        $criteria = new Criteria();
        $criteria->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_EXACT);
        foreach ($fields as $field) {
            $criteria->addGroupField(new FieldGrouping($field));
        }

        $request = null;
        $client = $this->createMock(Client::class);
        $client->expects($this->once())->method('search')->willReturnCallback(static function (array $params) use (&$request): array {
            $request = $params;

            return [];
        });

        $helper = static::createStub(ElasticsearchHelper::class);
        $helper->method('allowSearch')->willReturn(true);

        $criteriaParser = static::createStub(CriteriaParser::class);
        $criteriaParser->method('buildAccessor')->willReturnArgument(1);

        $searcher = new ElasticsearchEntitySearcher(
            $client,
            static::createStub(EntitySearcherInterface::class),
            $helper,
            $criteriaParser,
            static::createStub(AbstractElasticsearchSearchHydrator::class),
            new EventDispatcher(),
            '10s',
            'dfs_query_then_fetch',
            $precisionThreshold
        );

        $searcher->search(new ProductDefinition(), $criteria, Context::createDefaultContext());

        static::assertNotNull($request);
        $cardinality = $request['body']['aggregations']['total-count']['cardinality'];
        static::assertIsArray($cardinality['script']);
        static::assertSame($fields, $cardinality['script']['params']['fields']);
        static::assertStringContainsString('for (def field : params.fields)', $cardinality['script']['source']);
        static::assertStringContainsString('doc[field]', $cardinality['script']['source']);
        foreach ($fields as $field) {
            static::assertStringNotContainsString($field, $cardinality['script']['source']);
        }
        if ($precisionThreshold === null) {
            static::assertArrayNotHasKey('precision_threshold', $cardinality);
        } else {
            static::assertSame($precisionThreshold, $cardinality['precision_threshold']);
        }
    }

    /**
     * @return \Generator<string, array{list<string>, int|null}>
     */
    public static function groupingFieldsProvider(): \Generator
    {
        yield 'multiple grouping fields' => [['manufacturerId', 'displayGroup'], null];
        yield 'configured precision with three grouping fields' => [['manufacturerId', 'displayGroup', 'active'], 40000];
        yield 'quotes and backslashes in field names remain data' => [['customFields.brand\'name', 'customFields.brand\\name'], null];
    }

    public function testSearchWithGroupingDoesNotSendPrecisionThresholdByDefault(): void
    {
        $criteria = new Criteria();
        $criteria->setLimit(10);
        $criteria->addGroupField(new FieldGrouping('id'));
        $criteria->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_EXACT);

        $client = $this->createMock(Client::class);
        $client->expects($this->once())
            ->method('search')
            ->with(static::callback(static function (array $params): bool {
                $cardinality = $params['body']['aggregations']['total-count']['cardinality'] ?? null;

                return \is_array($cardinality)
                    && ($cardinality['field'] ?? null) === 'id'
                    && !\array_key_exists('precision_threshold', $cardinality);
            }))
            ->willReturn([]);

        $helper = static::createStub(ElasticsearchHelper::class);
        $helper->method('allowSearch')->willReturn(true);
        $helper->method('getIndexName')->willReturn('');

        $criteriaParser = static::createStub(CriteriaParser::class);
        $criteriaParser->method('buildAccessor')->willReturn('id');

        $searcher = new ElasticsearchEntitySearcher(
            $client,
            static::createStub(EntitySearcherInterface::class),
            $helper,
            $criteriaParser,
            static::createStub(AbstractElasticsearchSearchHydrator::class),
            new EventDispatcher(),
            '10s',
            'dfs_query_then_fetch',
            null
        );

        $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);

        $searcher->search(new ProductDefinition(), $criteria, Context::createDefaultContext());
    }

    public function testSearchWithGroupingSendsConfiguredPrecisionThreshold(): void
    {
        $criteria = new Criteria();
        $criteria->setLimit(10);
        $criteria->addGroupField(new FieldGrouping('id'));
        $criteria->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_EXACT);

        $client = $this->createMock(Client::class);
        $client->expects($this->once())
            ->method('search')
            ->with(static::callback(static function (array $params): bool {
                $cardinality = $params['body']['aggregations']['total-count']['cardinality'] ?? null;

                return \is_array($cardinality)
                    && ($cardinality['field'] ?? null) === 'id'
                    && ($cardinality['precision_threshold'] ?? null) === 40000;
            }))
            ->willReturn([]);

        $helper = static::createStub(ElasticsearchHelper::class);
        $helper->method('allowSearch')->willReturn(true);
        $helper->method('getIndexName')->willReturn('');

        $criteriaParser = static::createStub(CriteriaParser::class);
        $criteriaParser->method('buildAccessor')->willReturn('id');

        $searcher = new ElasticsearchEntitySearcher(
            $client,
            static::createStub(EntitySearcherInterface::class),
            $helper,
            $criteriaParser,
            static::createStub(AbstractElasticsearchSearchHydrator::class),
            new EventDispatcher(),
            '10s',
            'dfs_query_then_fetch',
            40000
        );

        $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);

        $searcher->search(new ProductDefinition(), $criteria, Context::createDefaultContext());
    }

    public function testSearchWithoutLimitLoadsEveryPage(): void
    {
        $ids = new IdsCollection();
        $pages = [
            [self::hit($ids->get('a')), self::hit($ids->get('b'))],
            [self::hit($ids->get('c'))],
        ];

        $requests = [];
        $client = $this->createMock(Client::class);
        $client->expects($this->exactly(2))
            ->method('search')
            ->willReturnCallback(static function (array $params) use (&$requests, &$pages): array {
                $requests[] = $params;

                return ['hits' => ['total' => ['value' => 3], 'hits' => array_shift($pages)]];
            });

        $criteria = new Criteria();
        $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);
        $criteria->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_EXACT);

        $result = $this->createSearcher($client, pageSize: 2)->search(new ProductDefinition(), $criteria, Context::createDefaultContext());

        static::assertSame([$ids->get('a'), $ids->get('b'), $ids->get('c')], $result->getIds());
        static::assertSame(3, $result->getTotal());

        static::assertArrayNotHasKey('search_after', $requests[0]['body']);
        static::assertTrue($requests[0]['body']['track_total_hits']);

        static::assertSame([1.0, $ids->get('b')], $requests[1]['body']['search_after']);
        static::assertSame(0, $requests[1]['body']['from']);
        static::assertFalse($requests[1]['body']['track_total_hits']);
    }

    public function testSearchWithoutLimitSendsOneRequestWhenTheFirstPageIsNotFull(): void
    {
        $client = $this->createMock(Client::class);
        $client->expects($this->once())
            ->method('search')
            ->willReturn(['hits' => ['hits' => [self::hit(Uuid::randomHex())]]]);

        $criteria = new Criteria();
        $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);

        $result = $this->createSearcher($client, pageSize: 2)->search(new ProductDefinition(), $criteria, Context::createDefaultContext());

        static::assertCount(1, $result->getIds());
    }

    public function testSearchWithoutLimitFailsInsteadOfReturningPartialResults(): void
    {
        $client = $this->createMock(Client::class);
        $client->expects($this->once())
            ->method('search')
            ->with(static::callback(static fn (array $params): bool => ($params['allow_partial_search_results'] ?? null) === false))
            ->willReturn(['hits' => ['hits' => []]]);

        $criteria = new Criteria();
        $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);

        $this->createSearcher($client, pageSize: 2)->search(new ProductDefinition(), $criteria, Context::createDefaultContext());
    }

    /**
     * @param list<array<string, array<string, string>>> $expectedSort
     */
    #[DataProvider('tieBreakerProvider')]
    public function testSearchWithoutLimitBreaksSortingTiesById(?FieldSort $sorting, array $expectedSort): void
    {
        $client = $this->createMock(Client::class);
        $client->expects($this->once())
            ->method('search')
            ->with(static::callback(static fn (array $params): bool => $params['body']['sort'] === $expectedSort))
            ->willReturn(['hits' => ['hits' => []]]);

        $criteria = new Criteria();
        $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);

        $this->createSearcher($client, pageSize: 2, sorting: $sorting)->search(new ProductDefinition(), $criteria, Context::createDefaultContext());
    }

    public static function tieBreakerProvider(): \Generator
    {
        yield 'a search without sorting keeps the relevance order' => [
            'sorting' => null,
            'expectedSort' => [['_score' => ['order' => 'desc']], ['id' => ['order' => 'asc']]],
        ];

        yield 'a sorted search keeps its sorting ahead of the id' => [
            'sorting' => new FieldSort('stock', FieldSort::DESC),
            'expectedSort' => [['stock' => ['order' => 'desc']], ['id' => ['order' => 'asc']]],
        ];
    }

    public function testGroupedSearchWithoutLimitIsSentAsOneRequest(): void
    {
        $client = $this->createMock(Client::class);
        $client->expects($this->once())
            ->method('search')
            ->with(static::callback(static fn (array $params): bool => !\array_key_exists('allow_partial_search_results', $params)
                && !\array_key_exists('sort', $params['body'])))
            ->willReturn(['hits' => ['hits' => []]]);

        $criteria = new Criteria();
        $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);
        $criteria->addGroupField(new FieldGrouping('displayGroup'));

        $this->createSearcher($client, pageSize: 2)->search(new ProductDefinition(), $criteria, Context::createDefaultContext());
    }

    private function createSearcher(Client $client, int $pageSize, ?FieldSort $sorting = null): ElasticsearchEntitySearcher
    {
        $helper = static::createStub(ElasticsearchHelper::class);
        $helper->method('allowSearch')->willReturn(true);

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(ElasticsearchEntitySearcherSearchEvent::class, static function (ElasticsearchEntitySearcherSearchEvent $event) use ($pageSize, $sorting): void {
            $event->getSearch()->setSize($pageSize);

            if ($sorting !== null) {
                $event->getSearch()->addSort($sorting);
            }
        });

        return new ElasticsearchEntitySearcher(
            $client,
            static::createStub(EntitySearcherInterface::class),
            $helper,
            static::createStub(CriteriaParser::class),
            new ElasticsearchEntitySearchHydrator(),
            $dispatcher,
            '10s',
            'query_then_fetch'
        );
    }

    /**
     * @return array{_id: string, _score: float, sort: list<float|string>}
     */
    private static function hit(string $id): array
    {
        return ['_id' => $id, '_score' => 1.0, 'sort' => [1.0, $id]];
    }
}
