<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Store\Services;

use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Shopware\Core\Framework\Store\Services\RetryFailedStoreRequestMiddleware;

/**
 * @internal
 */
#[CoversClass(RetryFailedStoreRequestMiddleware::class)]
class RetryFailedStoreRequestMiddlewareTest extends TestCase
{
    public function testRetryFor503(): void
    {
        $requestCount = 0;
        $client = $this->createClient([
            new Response(503),
            new Response(200),
        ], true, $requestCount);

        $response = $client->request('GET', '/');

        static::assertSame(200, $response->getStatusCode());
        static::assertSame(2, $requestCount);
    }

    public function testDoesNotRetryWithoutMiddleware(): void
    {
        $requestCount = 0;
        $client = $this->createClient([
            new Response(503),
            new Response(200),
        ], false, $requestCount);

        $response = $client->request('GET', '/', ['http_errors' => false]);

        static::assertSame(503, $response->getStatusCode());
        static::assertSame(1, $requestCount);
    }

    public function testReturns503AfterRetryLimitIsReached(): void
    {
        $requestCount = 0;
        $client = $this->createClient([
            new Response(503),
            new Response(503),
            new Response(503),
            new Response(503),
        ], true, $requestCount);

        $response = $client->request('GET', '/', ['http_errors' => false]);

        static::assertSame(503, $response->getStatusCode());
        static::assertSame(4, $requestCount);
    }

    /**
     * @param list<Response> $responses
     */
    private function createClient(array $responses, bool $withMiddleware, int &$requestCount): Client
    {
        $mockHandler = static function (RequestInterface $request, array $options) use (&$responses, &$requestCount): FulfilledPromise {
            ++$requestCount;

            return new FulfilledPromise(array_shift($responses));
        };

        $handler = HandlerStack::create($mockHandler);
        if ($withMiddleware) {
            $handler->push(new RetryFailedStoreRequestMiddleware());
        }

        $config = [
            'handler' => $handler,
        ];

        return new Client($config);
    }
}
