<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Test\Profiler;

use Elasticsearch\ClientBuilder;
use GuzzleHttp\Ring\Future\FutureArray;
use PHPUnit\Framework\TestCase;
use Shopware\Elasticsearch\Profiler\ClientProfiler;

/**
 * @covers \Shopware\Elasticsearch\Profiler\ClientProfiler
 *
 * @internal
 */
class ClientProfilerTest extends TestCase
{
    public function testProfiling(): void
    {
        $builder = new ClientBuilder();
        $builder->setHandler(function () {
            return new FutureArray(\React\Promise\resolve([
                'status' => 200,
                'body' => fopen('php://memory', 'rb'),
                'transfer_stats' => [
                    'total_time' => 0,
                ],
                'effective_url' => 'http://localhost:9200/test/_search',
            ]));
        });

        $profiler = new ClientProfiler($builder->build());

        $request = ['index' => 'test', 'body' => ['query' => ['match_all' => []]]];
        $profiler->search($request);

        static::assertCount(1, $profiler->getCalledRequests());
        $requests = $profiler->getCalledRequests();
        static::assertSame('http://localhost:9200/test/_search?', $requests[0]['url']);
        static::assertSame($request, $requests[0]['request']);

        $profiler->resetRequests();
        static::assertCount(0, $profiler->getCalledRequests());
    }
}
