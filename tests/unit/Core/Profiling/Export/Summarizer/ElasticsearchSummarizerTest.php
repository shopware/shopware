<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Profiling\Export\Summarizer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Profiling\Export\Summarizer\ElasticsearchSummarizer;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;
use Symfony\Component\HttpKernel\DataCollector\TimeDataCollector;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ElasticsearchSummarizer::class)]
class ElasticsearchSummarizerTest extends TestCase
{
    public function testSupportsTheCollectorByName(): void
    {
        static::assertTrue((new ElasticsearchSummarizer())->supports(new ElasticsearchCollectorStub()));
        static::assertFalse((new ElasticsearchSummarizer())->supports(new TimeDataCollector()));
    }

    public function testSummarizesRequestsWithoutBodies(): void
    {
        $data = [
            'enabled' => true,
            'time' => 0,
            'requests' => [
                [
                    'url' => 'http://opensearch:9200/sw_product/_search',
                    'request' => ['index' => 'sw_product', 'body' => ['query' => ['match_all' => (object) []]]],
                    'response' => ['took' => 12, 'hits' => ['total' => ['value' => 312, 'relation' => 'eq'], 'hits' => [['_id' => 'a'], ['_id' => 'b']]]],
                    'time' => 0.0281,
                    'backtrace' => 'ElasticsearchEntitySearcher:search',
                ],
                [
                    'url' => 'http://opensearch:9200/_msearch',
                    'request' => ['body' => [['index' => 'sw_product'], ['size' => 0]]],
                    'response' => ['took' => 3, 'responses' => [['hits' => []]]],
                    'time' => 0.002,
                    'backtrace' => 'ElasticsearchEntityAggregator:aggregate',
                    'client' => 'admin',
                ],
                [
                    'url' => 'http://opensearch:9200/_bulk',
                    'request' => ['body' => [['index' => []]]],
                    'response' => ['took' => 1, 'errors' => true],
                    'time' => 0.001,
                    'backtrace' => 'ElasticsearchIndexer:index',
                ],
            ],
        ];

        static::assertSame(
            [
                'enabled' => true,
                'requests' => 3,
                'timeMs' => 31.1,
                'calls' => [
                    ['url' => 'http://opensearch:9200/sw_product/_search', 'timeMs' => 28.1, 'caller' => 'ElasticsearchEntitySearcher:search', 'took' => 12, 'totalHits' => 312, 'returnedHits' => 2, 'bodyBytes' => 26],
                    ['url' => 'http://opensearch:9200/_msearch', 'client' => 'admin', 'timeMs' => 2.0, 'caller' => 'ElasticsearchEntityAggregator:aggregate', 'took' => 3, 'responses' => 1, 'bodyBytes' => 35],
                    ['url' => 'http://opensearch:9200/_bulk', 'timeMs' => 1.0, 'caller' => 'ElasticsearchIndexer:index', 'took' => 1, 'errors' => true, 'bodyBytes' => 14],
                ],
            ],
            (new ElasticsearchSummarizer())->summarize(new ElasticsearchCollectorStub(), $data),
        );
    }

    public function testHandlesADisabledCollector(): void
    {
        static::assertSame(
            ['enabled' => false, 'requests' => 0, 'timeMs' => 0.0, 'calls' => []],
            (new ElasticsearchSummarizer())->summarize(new ElasticsearchCollectorStub(), []),
        );
    }
}

/**
 * @internal
 */
class ElasticsearchCollectorStub extends DataCollector
{
    public function collect(Request $request, Response $response, ?\Throwable $exception = null): void
    {
    }

    public function getName(): string
    {
        return 'elasticsearch';
    }
}
