<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Elasticsearch\Profiler;

use GuzzleHttp\Ring\Future\FutureArray;
use OpenSearch\ClientBuilder;
use PHPUnit\Framework\TestCase;
use Shopware\Elasticsearch\Profiler\ClientProfiler;
use function React\Promise\resolve;

/**
 * @covers \Shopware\Elasticsearch\Profiler\ClientProfiler
 *
 * @internal
 */
class ClientProfilerTest extends TestCase
{
    /**
     * @dataProvider providerQueries
     *
     * @param string|array<string> $index
     */
    public function testProfiling($index, string $expectedUrl): void
    {
        $builder = new ClientBuilder();
        $builder->setHandler(function () {
            return new FutureArray(resolve([
                'status' => 200,
                'body' => fopen('php://memory', 'rb'),
                'transfer_stats' => [
                    'total_time' => 0,
                ],
                'effective_url' => 'http://localhost:9200/test/_search',
            ]));
        });

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
     * @return iterable<array<int, array<int, string>|string>>
     */
    public function providerQueries(): iterable
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
}
