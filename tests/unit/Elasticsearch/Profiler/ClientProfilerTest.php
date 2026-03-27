<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Elasticsearch\Profiler;

use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response;
use OpenSearch\Client;
use OpenSearch\EndpointFactory;
use OpenSearch\RequestFactory;
use OpenSearch\Serializers\SmartSerializer;
use OpenSearch\TransportFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Shopware\Elasticsearch\Framework\RoundRobinHostHttpClient;
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
        $profiler = new ClientProfiler($this->createClient());

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
        $profiler = new ClientProfiler($this->createClient());

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
        $profiler = new ClientProfiler($this->createClient());

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
        $profiler = new ClientProfiler($this->createClient());

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
            'http://localhost:9200/test%2Ctest2/_search',
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
            'http://localhost:9200/test%2Ctest2/_msearch',
        ];
    }

    private function createClient(): Client
    {
        $httpFactory = new HttpFactory();
        $serializer = new SmartSerializer();
        $requestFactory = new RequestFactory($httpFactory, $httpFactory, $httpFactory, $serializer);
        $httpClient = new RoundRobinHostHttpClient(new class implements ClientInterface {
            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                return new Response(200, ['Content-Type' => 'application/json'], '{}');
            }
        }, [new \GuzzleHttp\Psr7\Uri('http://localhost:9200/')]);
        $transport = (new TransportFactory())
            ->setHttpClient($httpClient)
            ->setRequestFactory($requestFactory)
            ->create();

        return new Client($transport, new EndpointFactory($serializer), []);
    }
}
