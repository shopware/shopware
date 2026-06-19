<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Elasticsearch\Profiler;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Uri;
use OpenSearch\EndpointFactory;
use OpenSearch\RequestFactory;
use OpenSearch\Serializers\SmartSerializer;
use OpenSearch\TransportFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Elasticsearch\Profiler\ClientProfiler;

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
        $profiler = $this->createClientProfiler();

        $request = ['index' => $index, 'body' => ['query' => ['match_all' => []]]];
        $profiler->search($request);

        static::assertCount(1, $profiler->getCalledRequests());
        $requests = $profiler->getCalledRequests();
        static::assertSame($expectedUrl, $requests[0]['url']);
        static::assertSame($request, $requests[0]['request']);

        $profiler->resetRequests();
        static::assertCount(0, $profiler->getCalledRequests());
    }

    /**
     * @param string|array<string> $index
     */
    #[DataProvider('providerMSearchQueries')]
    public function testMSearching(string|array $index, string $expectedUrl): void
    {
        $profiler = $this->createClientProfiler();

        $request = ['index' => $index, 'body' => ['query' => ['match_all' => []]]];
        $profiler->msearch($request);

        static::assertCount(1, $profiler->getCalledRequests());
        $requests = $profiler->getCalledRequests();
        static::assertSame($expectedUrl, $requests[0]['url']);
        static::assertSame($request, $requests[0]['request']);

        $profiler->resetRequests();
        static::assertCount(0, $profiler->getCalledRequests());
    }

    public function testBulk(): void
    {
        $index = 'testIndex';
        $profiler = $this->createClientProfiler();

        $request = ['index' => $index, 'body' => ['index' => ['_id' => 'XYZ'], ['field' => 'value']]];
        $profiler->bulk($request);

        static::assertCount(1, $profiler->getCalledRequests());
        $requests = $profiler->getCalledRequests();
        static::assertSame('http://localhost:9200/testIndex/_bulk', $requests[0]['url']);
        static::assertSame($request, $requests[0]['request']);

        $profiler->resetRequests();
        static::assertCount(0, $profiler->getCalledRequests());
    }

    public function testPutScript(): void
    {
        $profiler = $this->createClientProfiler();

        $params = ['id' => 'numeric_translated_field_sorting', 'body' => ['script' => ['lang' => 'painless', 'source' => 'return doc[params.field].value;']]];
        $profiler->putScript($params);

        static::assertCount(1, $profiler->getCalledRequests());
        $requests = $profiler->getCalledRequests();
        static::assertSame('http://localhost:9200/_scripts/numeric_translated_field_sorting', $requests[0]['url']);
        static::assertSame($params, $requests[0]['request']);

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
            'http://localhost:9200/test/_search',
        ];

        yield 'index array' => [
            ['test', 'test2'],
            'http://localhost:9200/test,test2/_search',
        ];
    }

    /**
     * @return iterable<array<int, array<int, string>|string>>
     */
    public static function providerMSearchQueries(): iterable
    {
        yield 'index string' => [
            'test',
            'http://localhost:9200/test/_msearch',
        ];

        yield 'index array' => [
            ['test', 'test2'],
            'http://localhost:9200/test,test2/_msearch',
        ];
    }

    private function createClientProfiler(): ClientProfiler
    {
        $httpFactory = new HttpFactory();
        $serializer = new SmartSerializer();
        $endpointFactory = new EndpointFactory($serializer);
        $requestFactory = new RequestFactory($httpFactory, $httpFactory, $httpFactory, $serializer);
        $httpClient = new GuzzleClient([
            'base_uri' => 'http://localhost:9200/',
            'handler' => HandlerStack::create(new MockHandler([new Response(200, ['Content-Type' => 'application/json'], '{}')])),
        ]);
        $transport = (new TransportFactory())
            ->setHttpClient($httpClient)
            ->setRequestFactory($requestFactory)
            ->create();

        $profiler = new ClientProfiler($transport, $endpointFactory, []);
        $profiler->setBaseUri(new Uri('http://localhost:9200'));

        return $profiler;
    }
}
