<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Elasticsearch\Profiler;

use GuzzleHttp\Ring\Future\FutureArray;
use OpenSearch\ClientBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Elasticsearch\Profiler\ClientProfiler;

use function React\Promise\resolve;

/**
 * @internal
 */
#[CoversClass(ClientProfiler::class)]
class ClientProfilerTest extends TestCase
{
    /**
     * @param string|array<string> $index
     */
    #[DataProvider('providerSearchQueries')]
    public function testSearching(string|array $index, string $expectedUrl): void
    {
        $builder = new ClientBuilder();
        $builder->setHandler(fn () => new FutureArray(resolve([
            'status' => 200,
            'body' => fopen('php://memory', 'r'),
            'transfer_stats' => [
                'total_time' => 0,
            ],
            'effective_url' => 'http://localhost:9200/test/_search',
        ])));

        $profiler = new ClientProfiler($builder->build());

        $request = ['index' => $index, 'body' => ['query' => ['match_all' => []]]];
        $profiler->search($request);

        static::assertCount(1, $profiler->getCalledRequests());
        $requests = $profiler->getCalledRequests();
        static::assertSame($expectedUrl, $requests[0]['url']);
        static::assertEquals($request, $requests[0]['request']);

        $profiler->resetRequests();
        static::assertCount(0, $profiler->getCalledRequests());
    }

    /**
     * @param string|array<string> $index
     */
    #[DataProvider('providerMSearchQueries')]
    public function testMSearching(string|array $index, string $expectedUrl): void
    {
        $builder = new ClientBuilder();
        $builder->setHandler(fn () => new FutureArray(resolve([
            'status' => 200,
            'body' => fopen('php://memory', 'r'),
            'transfer_stats' => [
                'total_time' => 0,
            ],
            'effective_url' => 'http://localhost:9200/_msearch',
        ])));

        $profiler = new ClientProfiler($builder->build());

        $request = ['index' => $index, 'body' => ['query' => ['match_all' => []]]];
        $profiler->msearch($request);

        static::assertCount(1, $profiler->getCalledRequests());
        $requests = $profiler->getCalledRequests();
        static::assertSame($expectedUrl, $requests[0]['url']);
        static::assertEquals($request, $requests[0]['request']);

        $profiler->resetRequests();
        static::assertCount(0, $profiler->getCalledRequests());
    }

    public function testBulk(): void
    {
        $index = 'testIndex';
        $builder = new ClientBuilder();
        $builder->setHandler(fn () => new FutureArray(resolve([
            'status' => 200,
            'body' => fopen('php://memory', 'r'),
            'transfer_stats' => [
                'total_time' => 0,
            ],
            'effective_url' => 'http://localhost:9200/_bulk',
        ])));

        $profiler = new ClientProfiler($builder->build());

        $request = ['index' => $index, 'body' => ['index' => ['_id' => 'XYZ'], ['field' => 'value']]];
        $profiler->bulk($request);

        static::assertCount(1, $profiler->getCalledRequests());
        $requests = $profiler->getCalledRequests();
        static::assertSame('http://localhost:9200/_bulk', $requests[0]['url']);
        static::assertEquals($request, $requests[0]['request']);

        $profiler->resetRequests();
        static::assertCount(0, $profiler->getCalledRequests());
    }

    public function testPutScript(): void
    {
        $builder = new ClientBuilder();
        $builder->setHandler(fn () => new FutureArray(resolve([
            'status' => 200,
            'body' => fopen('php://memory', 'r'),
            'transfer_stats' => [
                'total_time' => 0,
            ],
            'effective_url' => 'http://localhost:9200/_scripts',
        ])));

        $profiler = new ClientProfiler($builder->build());

        $params = ['id' => 'numeric_translated_field_sorting', 'body' => ['script' => ['lang' => 'painless', 'source' => 'return doc[params.field].value;']]];
        $profiler->putScript($params);

        static::assertCount(1, $profiler->getCalledRequests());
        $requests = $profiler->getCalledRequests();
        static::assertSame('http://localhost:9200/_scripts/numeric_translated_field_sorting', $requests[0]['url']);
        static::assertEquals($params, $requests[0]['request']);

        $profiler->resetRequests();
        static::assertCount(0, $profiler->getCalledRequests());
    }

    /**
     * @return iterable<array<int, array<int, string>|string>>
     */
    public static function providerSearchQueries(): iterable
    {
        yield 'index string' => [
            'test',
            'http://localhost:9200/test/_search?',
        ];

        yield 'index array' => [
            ['test', 'test2'],
            'http://localhost:9200/test,test2/_search?',
        ];
    }

    /**
     * @return iterable<array<int, array<int, string>|string>>
     */
    public static function providerMSearchQueries(): iterable
    {
        yield 'index string' => [
            'test',
            'http://localhost:9200/_msearch',
        ];

        yield 'index array' => [
            ['test', 'test2'],
            'http://localhost:9200/_msearch',
        ];
    }
}
