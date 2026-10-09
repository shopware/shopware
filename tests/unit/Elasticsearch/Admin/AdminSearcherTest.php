<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Elasticsearch\Admin;

use Doctrine\DBAL\Connection;
use OpenSearch\Client;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Content\Flow\FlowCollection;
use Shopware\Core\Content\Flow\FlowDefinition;
use Shopware\Core\Content\Flow\FlowEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\SearchRanking;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Elasticsearch\Admin\AdminElasticsearchHelper;
use Shopware\Elasticsearch\Admin\AdminSearcher;
use Shopware\Elasticsearch\Admin\AdminSearchRegistry;
use Shopware\Elasticsearch\Admin\Indexer\AbstractAdminIndexer;
use Shopware\Elasticsearch\Admin\Indexer\ProductAdminSearchIndexer;
use Shopware\Elasticsearch\ElasticsearchException;
use Shopware\Elasticsearch\Framework\DataAbstractionLayer\AbstractElasticsearchSearchHydrator;
use Shopware\Elasticsearch\Framework\ElasticsearchFieldBuilder;
use Shopware\Elasticsearch\Framework\ElasticsearchHelper;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(AdminSearcher::class)]
class AdminSearcherTest extends TestCase
{
    private Client&MockObject $client;

    private AdminSearcher $searcher;

    private AdminSearchRegistry&Stub $registry;

    private AbstractAdminIndexer $productIndexer;

    protected function setUp(): void
    {
        $this->client = $this->createMock(Client::class);

        $this->registry = static::createStub(AdminSearchRegistry::class);

        $this->productIndexer = new ProductAdminSearchIndexer(
            static::createStub(Connection::class),
            static::createStub(IteratorFactory::class),
            static::createStub(EntityRepository::class),
            static::createStub(ElasticsearchFieldBuilder::class),
            100
        );
        $this->registry->method('getIndexers')->willReturn(['product' => $this->productIndexer]);
        $this->registry->method('hasIndexer')->willReturn(true);
        $this->registry->method('getIndexer')->willReturn($this->productIndexer);

        $searchHelper = new AdminElasticsearchHelper(true, false, 'sw-admin', 'test', true, new NullLogger());
        $this->searcher = new AdminSearcher(
            $this->client,
            $this->registry,
            $searchHelper,
            static::createStub(DefinitionInstanceRegistry::class),
            static::createStub(AbstractElasticsearchSearchHydrator::class),
            static::createStub(ElasticsearchHelper::class),
            '5s',
            20,
            'query_then_fetch',
        );
    }

    public function testElasticSearch(): void
    {
        $this->client
            ->expects($this->once())
            ->method('msearch')
            ->with($this->getQueryBody('elasticsearch*'))
            ->willReturn($this->getMockResponse('c1a28776116d4431a2208eb2960ec340 elasticsearch'));

        $data = $this->searcher->search('elasticsearch', ['product'], Context::createDefaultContext());

        $this->assertSearchResult($data, 1, 'product-listing', 'sw-admin-product-listing');
    }

    public function testSearchWithLimit(): void
    {
        $this->client
            ->expects($this->once())
            ->method('msearch')
            ->with($this->getQueryBody('elast*', '1s'))
            ->willReturn($this->getMockResponse('c1a28776116d4431a2208eb2960ec340 elasticsearch'));

        $searchHelper = new AdminElasticsearchHelper(true, false, 'sw-admin', 'test', true, new NullLogger());
        $searcher = new AdminSearcher(
            $this->client,
            $this->registry,
            $searchHelper,
            static::createStub(DefinitionInstanceRegistry::class),
            static::createStub(AbstractElasticsearchSearchHydrator::class),
            static::createStub(ElasticsearchHelper::class),
            '1s',
            5,
            'query_then_fetch',
        );

        $data = $searcher->search('elasticsearch', ['product'], Context::createDefaultContext());

        $this->assertSearchResult($data, 1, 'product-listing', 'sw-admin-product-listing');
    }

    public function testSearchWithUndefinedIndexerAndUnknownEntity(): void
    {
        $registry = static::createStub(AdminSearchRegistry::class);
        $registry->method('hasIndexer')->willReturn(false);

        $this->client->expects($this->never())->method('msearch');

        $searcher = $this->createSearcher($registry, static::createStub(DefinitionInstanceRegistry::class));

        $data = $searcher->search('elasticsearch', ['test'], Context::createDefaultContext());

        static::assertEmpty($data);
    }

    public function testSearchFallsBackToTheDalWhenTheEntityHasNoIndexer(): void
    {
        $registry = static::createStub(AdminSearchRegistry::class);
        $registry->method('hasIndexer')->willReturn(false);

        $this->client->expects($this->never())->method('msearch');

        $flow = new FlowEntity();
        $flow->setUniqueIdentifier(Uuid::randomHex());

        $searchedCriteria = null;
        $repository = StaticEntityRepository::of(
            FlowCollection::class,
            [
                function (Criteria $criteria) use (&$searchedCriteria, $flow): FlowCollection {
                    $searchedCriteria = $criteria;

                    return new FlowCollection([$flow]);
                },
            ],
            new FlowDefinition()
        );

        $definitionRegistry = static::createStub(DefinitionInstanceRegistry::class);
        $definitionRegistry->method('has')->willReturn(true);
        $definitionRegistry->method('getRepository')->willReturn($repository);

        $searcher = $this->createSearcher($registry, $definitionRegistry);

        $data = $searcher->search('Order and placed', ['flow'], Context::createDefaultContext());

        static::assertSame(1, $data['flow']['total']);
        static::assertSame([$flow], array_values($data['flow']['data']->getElements()));

        static::assertInstanceOf(Criteria::class, $searchedCriteria);
        // raw term, not the elasticsearch operator syntax where "and" becomes "+"
        static::assertSame('Order and placed', $searchedCriteria->getTerm());
        static::assertSame(5, $searchedCriteria->getLimit());
    }

    public function testSearchFallsBackToTheDalWhenTheIndexerCannotBeResolved(): void
    {
        $registry = static::createStub(AdminSearchRegistry::class);
        $registry->method('hasIndexer')->willReturn(true);
        $registry->method('getIndexer')->willReturnCallback(function (string $entityName): AbstractAdminIndexer {
            if ($entityName === 'flow') {
                throw ElasticsearchException::indexingError(['Indexer for name flow not found']);
            }

            return $this->productIndexer;
        });

        $this->client
            ->expects($this->once())
            ->method('msearch')
            ->with($this->getQueryBody('elasticsearch*'))
            ->willReturn($this->getMockResponse('c1a28776116d4431a2208eb2960ec340 elasticsearch'));

        $flow = new FlowEntity();
        $flow->setUniqueIdentifier(Uuid::randomHex());

        $definitionRegistry = static::createStub(DefinitionInstanceRegistry::class);
        $definitionRegistry->method('has')->willReturn(true);
        $definitionRegistry->method('getRepository')->willReturn(
            StaticEntityRepository::of(FlowCollection::class, [new FlowCollection([$flow])], new FlowDefinition())
        );

        $searcher = $this->createSearcher($registry, $definitionRegistry);

        $data = $searcher->search('elasticsearch', ['product', 'flow'], Context::createDefaultContext());

        $this->assertSearchResult($data, 1, 'product-listing', 'sw-admin-product-listing');
        static::assertSame(1, $data['flow']['total']);
    }

    public function testSearchWithNumericTerm(): void
    {
        $this->client
            ->expects($this->once())
            ->method('msearch')
            ->with($this->getQueryBody('3800*'))
            ->willReturn($this->getMockResponse('product x3800'));

        $data = $this->searcher->search('3800', ['product'], Context::createDefaultContext());

        $this->assertSearchResult($data, 1, 'product-listing', 'sw-admin-product-listing');
    }

    public function testSearchWithMixedTermContainingNumeric(): void
    {
        $this->client
            ->expects($this->once())
            ->method('msearch')
            ->with($this->getQueryBody('product 3800*'))
            ->willReturn($this->getMockResponse('product 3800'));

        $data = $this->searcher->search('product 3800', ['product'], Context::createDefaultContext());

        static::assertNotEmpty($data['product']);
        static::assertSame(1, $data['product']['total']);
    }

    public function testSearchWithPureNumeric(): void
    {
        $this->client
            ->expects($this->once())
            ->method('msearch')
            ->with($this->getQueryBody('123*'))
            ->willReturn($this->getMockResponse('product 123'));

        $data = $this->searcher->search('123', ['product'], Context::createDefaultContext());

        static::assertNotEmpty($data['product']);
        static::assertSame(1, $data['product']['total']);
    }

    public function testSearchNormalizesTermLevelQueries(): void
    {
        $this->client
            ->expects($this->once())
            ->method('msearch')
            ->with($this->getQueryBody('LAPTO*'))
            ->willReturn($this->getMockResponse('laptop computer'));

        $data = $this->searcher->search('LAPTO', ['product'], Context::createDefaultContext());

        static::assertNotEmpty($data['product']);
        static::assertSame(1, $data['product']['total']);
    }

    public function testSearchReturnsEmptyResultWhenClientFailsAndExceptionsAreSuppressed(): void
    {
        $this->client
            ->expects($this->once())
            ->method('msearch')
            ->willThrowException(new \RuntimeException('No alive nodes found in your cluster'));

        $searchHelper = new AdminElasticsearchHelper(true, false, 'sw-admin', 'prod', false, new NullLogger());
        $searcher = new AdminSearcher(
            $this->client,
            $this->registry,
            $searchHelper,
            static::createStub(DefinitionInstanceRegistry::class),
            static::createStub(AbstractElasticsearchSearchHydrator::class),
            static::createStub(ElasticsearchHelper::class),
            '5s',
            20,
            'query_then_fetch',
        );

        $data = $searcher->search('elasticsearch', ['product'], Context::createDefaultContext());

        static::assertSame([], $data);
    }

    public function testSearchThrowsWhenClientFailsAndExceptionsAreEnabled(): void
    {
        $exception = new \RuntimeException('No alive nodes found in your cluster');

        $this->client
            ->expects($this->once())
            ->method('msearch')
            ->willThrowException($exception);

        $this->expectExceptionObject($exception);

        $this->searcher->search('elasticsearch', ['product'], Context::createDefaultContext());
    }

    private function createSearcher(AdminSearchRegistry&Stub $registry, DefinitionInstanceRegistry&Stub $definitionRegistry): AdminSearcher
    {
        return new AdminSearcher(
            $this->client,
            $registry,
            new AdminElasticsearchHelper(true, false, 'sw-admin', 'test', true, new NullLogger()),
            $definitionRegistry,
            static::createStub(AbstractElasticsearchSearchHydrator::class),
            static::createStub(ElasticsearchHelper::class),
            '5s',
            20,
            'query_then_fetch',
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function getQueryBody(string $query, string $timeout = '5s'): array
    {
        $originalTerm = rtrim($query, '*');
        $splitTerms = explode(' ', $originalTerm);
        $lastPart = (string) end($splitTerms);
        $termLevelTerm = mb_strtolower($originalTerm);
        $termLevelPrefixTerm = mb_strtolower($lastPart);
        $shouldQueries = [
            [
                'match' => [
                    'completion' => [
                        'query' => $originalTerm,
                        'boost' => SearchRanking::HIGH_SEARCH_RANKING,
                    ],
                ],
            ],
            [
                'match' => [
                    'completion.ngram' => [
                        'query' => $originalTerm,
                        'boost' => SearchRanking::LOW_SEARCH_RANKING,
                    ],
                ],
            ],
            [
                'prefix' => [
                    'completion' => [
                        'value' => $termLevelPrefixTerm,
                        'boost' => SearchRanking::MIDDLE_SEARCH_RANKING,
                    ],
                ],
            ],
            [
                'simple_query_string' => [
                    'query' => $query,
                    'fields' => ['text'],
                    'lenient' => true,
                    'boost' => SearchRanking::LOW_SEARCH_RANKING,
                ],
            ],
            [
                'term' => [
                    'ean' => [
                        'boost' => SearchRanking::HIGH_SEARCH_RANKING,
                        'value' => $termLevelTerm,
                    ],
                ],
            ],
            [
                'term' => [
                    'productNumber' => [
                        'boost' => SearchRanking::HIGH_SEARCH_RANKING,
                        'value' => $termLevelTerm,
                    ],
                ],
            ],
            [
                'term' => [
                    'manufacturerNumber' => [
                        'boost' => SearchRanking::HIGH_SEARCH_RANKING,
                        'value' => $termLevelTerm,
                    ],
                ],
            ],
        ];

        $shouldQueries[] = [
            'simple_query_string' => [
                'query' => $query,
                'fields' => ['textBoosted'],
                'boost' => SearchRanking::HIGH_SEARCH_RANKING,
                'lenient' => true,
            ],
        ];

        return [
            'body' => [
                [
                    'index' => 'sw-admin-product-listing',
                    'search_type' => 'query_then_fetch',
                    'allow_no_indices' => true,
                    'ignore_unavailable' => true,
                ],
                [
                    'query' => [
                        'bool' => [
                            'should' => $shouldQueries,
                        ],
                    ],
                    'size' => 5,
                    'timeout' => $timeout,
                ],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    private function assertSearchResult(array $data, int $total, string $indexer, string $index): void
    {
        static::assertNotEmpty($data['product']);
        static::assertSame($total, $data['product']['total']);
        static::assertSame($indexer, $data['product']['indexer']);
        static::assertSame($index, $data['product']['index']);
    }

    /**
     * @return array<string, mixed>
     */
    private function getMockResponse(string $text): array
    {
        return [
            'took' => 42,
            'responses' => [
                [
                    'took' => 42,
                    'timed_out' => false,
                    '_shards' => [
                        'total' => 1,
                        'successful' => 1,
                        'skipped' => 0,
                        'failed' => 0,
                    ],
                    'hits' => [
                        'total' => [
                            'value' => 1,
                            'relation' => 'eq',
                        ],
                        'max_score' => 4.9525366,
                        'hits' => [
                            [
                                '_index' => 'sw-admin-product-listing',
                                '_type' => '_doc',
                                '_id' => 'c1a28776116d4431a2208eb2960ec340',
                                '_score' => 4.9525366,
                                '_source' => [
                                    'entityName' => 'product',
                                    'parameters' => [],
                                    'text' => $text,
                                    'id' => 'c1a28776116d4431a2208eb2960ec340',
                                ],
                            ],
                        ],
                    ],
                    'status' => 200,
                ],
            ],
        ];
    }
}
