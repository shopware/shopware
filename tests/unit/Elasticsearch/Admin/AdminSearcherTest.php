<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Elasticsearch\Admin;

use Doctrine\DBAL\Connection;
use OpenSearch\Client;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\SearchRanking;
use Shopware\Elasticsearch\Admin\AdminElasticsearchHelper;
use Shopware\Elasticsearch\Admin\AdminSearcher;
use Shopware\Elasticsearch\Admin\AdminSearchRegistry;
use Shopware\Elasticsearch\Admin\Indexer\ProductAdminSearchIndexer;
use Shopware\Elasticsearch\ElasticsearchException;
use Shopware\Elasticsearch\Framework\DataAbstractionLayer\AbstractElasticsearchSearchHydrator;
use Shopware\Elasticsearch\Framework\ElasticsearchFieldBuilder;
use Shopware\Elasticsearch\Framework\ElasticsearchHelper;

/**
 * @internal
 */
#[CoversClass(AdminSearcher::class)]
class AdminSearcherTest extends TestCase
{
    private Client&MockObject $client;

    private AdminSearcher $searcher;

    private AdminSearchRegistry&Stub $registry;

    protected function setUp(): void
    {
        $this->client = $this->createMock(Client::class);

        $this->registry = static::createStub(AdminSearchRegistry::class);

        $indexer = new ProductAdminSearchIndexer(
            $this->createMock(Connection::class),
            $this->createMock(IteratorFactory::class),
            $this->createMock(EntityRepository::class),
            $this->createMock(ElasticsearchFieldBuilder::class),
            100
        );
        $this->registry->method('getIndexers')->willReturn(['product' => $indexer]);
        $this->registry->method('getIndexer')->willReturn($indexer);

        $searchHelper = new AdminElasticsearchHelper(true, false, 'sw-admin', 'test', true, new NullLogger());
        $this->searcher = new AdminSearcher(
            $this->client,
            $this->registry,
            $searchHelper,
            $this->createMock(DefinitionInstanceRegistry::class),
            $this->createMock(AbstractElasticsearchSearchHydrator::class),
            $this->createMock(ElasticsearchHelper::class),
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
            $this->createMock(DefinitionInstanceRegistry::class),
            $this->createMock(AbstractElasticsearchSearchHydrator::class),
            $this->createMock(ElasticsearchHelper::class),
            '1s',
            5,
            'query_then_fetch',
        );

        $data = $searcher->search('elasticsearch', ['product'], Context::createDefaultContext());

        $this->assertSearchResult($data, 1, 'product-listing', 'sw-admin-product-listing');
    }

    public function testSearchWithUndefinedIndexer(): void
    {
        $this->registry->method('getIndexer')->willThrowException(ElasticsearchException::indexingError(['Indexer for name test not found']));

        $searchHelper = new AdminElasticsearchHelper(true, false, 'sw-admin', 'test', true, new NullLogger());
        $searcher = new AdminSearcher(
            $this->client,
            $this->registry,
            $searchHelper,
            $this->createMock(DefinitionInstanceRegistry::class),
            $this->createMock(AbstractElasticsearchSearchHydrator::class),
            $this->createMock(ElasticsearchHelper::class),
            '5s',
            20,
            'query_then_fetch',
        );

        $data = $searcher->search('elasticsearch', ['test'], Context::createDefaultContext());

        static::assertEmpty($data);
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
