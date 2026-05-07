<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Webhook\Service;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Shopware\Core\Framework\App\AppLocaleProvider;
use Shopware\Core\Framework\App\Hmac\Guzzle\AuthMiddleware;
use Shopware\Core\Framework\App\Hmac\RequestSigner;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Webhook\Service\WebhookClient;
use Shopware\Core\Framework\Webhook\Service\WebhookRequest;
use Symfony\Component\Clock\NativeClock;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(WebhookClient::class)]
class WebhookClientTest extends TestCase
{
    public function testSendSuccessful(): void
    {
        $responseBody = ['status' => 'ok'];
        $mockHandler = new MockHandler([
            new Response(200, ['X-Response-Header' => 'value'], json_encode($responseBody, \JSON_THROW_ON_ERROR)),
        ]);

        $client = $this->createClient($mockHandler);
        $webhookRequest = $this->createWebhookRequest();

        $result = $client->send($webhookRequest);

        static::assertTrue($result->successful());
        static::assertSame(200, $result->statusCode);
        static::assertSame('OK', $result->reasonPhrase);
        static::assertSame($responseBody, $result->body);
        static::assertNotNull($result->headers);
        static::assertArrayHasKey('X-Response-Header', $result->headers);
        static::assertNull($result->errorMessage);

        $request = $mockHandler->getLastRequest();
        static::assertInstanceOf(RequestInterface::class, $request);
        $this->assertRequestHasCorrectHeaders($request);
        $this->assertRequestIsSigned($request);
    }

    public function testSendWithCustomHeaders(): void
    {
        $mockHandler = new MockHandler([new Response(200, [], '{}')]);
        $client = $this->createClient($mockHandler);

        $webhookRequest = $this->createWebhookRequest(headers: ['X-Custom' => 'custom-value', 'X-Another' => 'another']);

        $client->send($webhookRequest);

        $request = $mockHandler->getLastRequest();
        static::assertInstanceOf(RequestInterface::class, $request);
        static::assertSame('custom-value', $request->getHeaderLine('X-Custom'));
        static::assertSame('another', $request->getHeaderLine('X-Another'));
    }

    public function testSendReturnsFailureResultOnHttpError(): void
    {
        $errorBody = ['error' => 'Bad request'];
        $mockHandler = new MockHandler([
            new RequestException(
                'Bad Request',
                new Request('POST', 'https://example.com'),
                new Response(400, ['Content-Type' => 'application/json'], json_encode($errorBody, \JSON_THROW_ON_ERROR))
            ),
        ]);

        $client = $this->createClient($mockHandler);
        $result = $client->send($this->createWebhookRequest());

        static::assertFalse($result->successful());
        static::assertTrue($result->hasResponse());
        static::assertSame(400, $result->statusCode);
        static::assertSame('Bad Request', $result->reasonPhrase);
        static::assertSame($errorBody, $result->body);
        static::assertNotNull($result->headers);
        static::assertSame('Bad Request', $result->errorMessage);
    }

    public function testSendReturnsFailureResultOnNetworkError(): void
    {
        $mockHandler = new MockHandler([
            new ConnectException('Connection refused', new Request('POST', 'https://example.com')),
        ]);

        $client = $this->createClient($mockHandler);
        $result = $client->send($this->createWebhookRequest());

        static::assertFalse($result->successful());
        static::assertFalse($result->hasResponse());
        static::assertNull($result->statusCode);
        static::assertSame('Connection refused', $result->errorMessage);
    }

    public function testSendBatch(): void
    {
        $history = [];
        $historyMiddleware = Middleware::history($history);

        $mockHandler = new MockHandler([
            new Response(200, [], '{}'),
            new Response(201, [], '{}'),
            new Response(202, [], '{}'),
        ]);

        $handlerStack = HandlerStack::create($mockHandler);
        $handlerStack->push(new AuthMiddleware('6.7.0', $this->createMock(AppLocaleProvider::class)));
        $handlerStack->push($historyMiddleware);
        $guzzle = new Client(['handler' => $handlerStack]);
        $client = new WebhookClient($guzzle, new NativeClock());

        $requests = [
            'hook1' => $this->createWebhookRequest(url: 'https://example.com/hook1', headers: ['X-Custom' => 'value1']),
            'hook2' => $this->createWebhookRequest(url: 'https://example.com/hook2'),
            'hook3' => $this->createWebhookRequest(url: 'https://example.com/hook3', secret: null),
        ];

        $results = $client->sendBatch($requests);

        static::assertIsArray($history);
        static::assertCount(3, $history);
        static::assertSame(['hook1', 'hook2', 'hook3'], array_keys($results));
        static::assertTrue($results['hook1']->successful());
        static::assertSame(200, $results['hook1']->statusCode);
        static::assertTrue($results['hook2']->successful());
        static::assertSame(201, $results['hook2']->statusCode);
        static::assertTrue($results['hook3']->successful());
        static::assertSame(202, $results['hook3']->statusCode);

        // Assert first request
        $request1 = $history[0]['request'];
        static::assertInstanceOf(RequestInterface::class, $request1);
        static::assertSame('https://example.com/hook1', (string) $request1->getUri());
        static::assertSame('POST', $request1->getMethod());
        $this->assertRequestHasCorrectHeaders($request1);
        static::assertSame('value1', $request1->getHeaderLine('X-Custom'));
        $this->assertRequestIsSigned($request1);

        // Assert second request
        $request2 = $history[1]['request'];
        static::assertInstanceOf(RequestInterface::class, $request2);
        static::assertSame('https://example.com/hook2', (string) $request2->getUri());
        $this->assertRequestIsSigned($request2);

        // Assert third request (no secret, should not be signed)
        $request3 = $history[2]['request'];
        static::assertInstanceOf(RequestInterface::class, $request3);
        static::assertSame('https://example.com/hook3', (string) $request3->getUri());
        static::assertFalse($request3->hasHeader(RequestSigner::SHOPWARE_SHOP_SIGNATURE));
    }

    public function testSendBatchReturnsFailureResults(): void
    {
        $history = [];
        $historyMiddleware = Middleware::history($history);

        $mockHandler = new MockHandler([
            new ConnectException('Connection refused', new Request('POST', 'https://example.com/hook1')),
            new Response(200, [], '{}'),
        ]);

        $handlerStack = HandlerStack::create($mockHandler);
        $handlerStack->push(new AuthMiddleware('6.7.0', $this->createMock(AppLocaleProvider::class)));
        $handlerStack->push($historyMiddleware);

        $client = new WebhookClient(new Client(['handler' => $handlerStack]), new NativeClock());

        $results = $client->sendBatch([
            'hook1' => $this->createWebhookRequest(url: 'https://example.com/hook1'),
            'hook2' => $this->createWebhookRequest(url: 'https://example.com/hook2'),
        ]);

        static::assertIsArray($history);
        static::assertCount(2, $history);
        static::assertIsArray($history);
        static::assertCount(2, $results);
        static::assertFalse($results['hook1']->successful());
        static::assertNull($results['hook1']->statusCode);
        static::assertSame('Connection refused', $results['hook1']->errorMessage);
        static::assertTrue($results['hook2']->successful());
        static::assertSame(200, $results['hook2']->statusCode);
    }

    public function testSendBatchWithEmptyListDoesNothing(): void
    {
        $mockHandler = new MockHandler([]);
        $client = $this->createClient($mockHandler);

        static::assertSame([], $client->sendBatch([]));

        static::assertNull($mockHandler->getLastRequest());
    }

    public function testSendWithScalarJsonBodyReturnsDecodedValue(): void
    {
        $mockHandler = new MockHandler([
            new Response(200, [], 'true'),
        ]);

        $client = $this->createClient($mockHandler);
        $result = $client->send($this->createWebhookRequest());

        static::assertTrue($result->successful());
        static::assertSame(200, $result->statusCode);
        static::assertTrue($result->body);
    }

    public function testSendWithScalarJsonErrorBodyReturnsDecodedValue(): void
    {
        $mockHandler = new MockHandler([
            new RequestException(
                'Bad Request',
                new Request('POST', 'https://example.com'),
                new Response(400, [], '123')
            ),
        ]);

        $client = $this->createClient($mockHandler);
        $result = $client->send($this->createWebhookRequest());

        static::assertFalse($result->successful());
        static::assertTrue($result->hasResponse());
        static::assertSame(400, $result->statusCode);
        static::assertSame(123, $result->body);
    }

    public function testSendWithEmptyBodyReturnsNull(): void
    {
        $mockHandler = new MockHandler([
            new Response(204, [], ''),
        ]);

        $client = $this->createClient($mockHandler);
        $result = $client->send($this->createWebhookRequest());

        static::assertTrue($result->successful());
        static::assertSame(204, $result->statusCode);
        static::assertNull($result->body);
    }

    public function testSendWithNonJsonTextBodyReturnsNull(): void
    {
        $mockHandler = new MockHandler([
            new Response(200, [], '<html>OK</html>'),
        ]);

        $client = $this->createClient($mockHandler);
        $result = $client->send($this->createWebhookRequest());

        static::assertTrue($result->successful());
        static::assertSame(200, $result->statusCode);
        static::assertNull($result->body);
    }

    public function testSendSuccessRecordsProcessingTimeSeconds(): void
    {
        $mockHandler = new MockHandler([
            new Response(200, [], '{}'),
        ]);

        $client = $this->createClient($mockHandler);
        $result = $client->send($this->createWebhookRequest());

        static::assertNotNull($result->processingTimeSeconds);
        static::assertGreaterThanOrEqual(0, $result->processingTimeSeconds);
    }

    public function testSendFailureRecordsProcessingTimeSeconds(): void
    {
        $mockHandler = new MockHandler([
            new ConnectException('Connection refused', new Request('POST', 'https://example.com')),
        ]);

        $client = $this->createClient($mockHandler);
        $result = $client->send($this->createWebhookRequest());

        static::assertFalse($result->successful());
        static::assertNotNull($result->processingTimeSeconds);
        static::assertGreaterThanOrEqual(0, $result->processingTimeSeconds);
    }

    public function testSendBatchFulfilledRecordsProcessingTimeSeconds(): void
    {
        $mockHandler = new MockHandler([
            new Response(200, [], '{}'),
            new Response(201, [], '{}'),
        ]);

        $client = $this->createClient($mockHandler);

        $results = $client->sendBatch([
            'hook1' => $this->createWebhookRequest(url: 'https://example.com/hook1'),
            'hook2' => $this->createWebhookRequest(url: 'https://example.com/hook2'),
        ]);

        static::assertCount(2, $results);
        foreach ($results as $key => $result) {
            static::assertTrue($result->successful(), \sprintf('Expected result "%s" to be successful', $key));
            static::assertNotNull($result->processingTimeSeconds, \sprintf('Expected result "%s" to have processingTimeSeconds', $key));
            static::assertGreaterThanOrEqual(0, $result->processingTimeSeconds, \sprintf('Expected result "%s" processingTimeSeconds >= 0', $key));
        }
    }

    public function testSendBatchRejectedRecordsProcessingTimeSeconds(): void
    {
        $mockHandler = new MockHandler([
            new ConnectException('Connection refused', new Request('POST', 'https://example.com/hook1')),
            new ConnectException('Timeout', new Request('POST', 'https://example.com/hook2')),
        ]);

        $client = $this->createClient($mockHandler);

        $results = $client->sendBatch([
            'hook1' => $this->createWebhookRequest(url: 'https://example.com/hook1'),
            'hook2' => $this->createWebhookRequest(url: 'https://example.com/hook2'),
        ]);

        static::assertCount(2, $results);
        foreach ($results as $key => $result) {
            static::assertFalse($result->successful(), \sprintf('Expected result "%s" to be a failure', $key));
            static::assertNotNull($result->processingTimeSeconds, \sprintf('Expected result "%s" to have processingTimeSeconds', $key));
            static::assertGreaterThanOrEqual(0, $result->processingTimeSeconds, \sprintf('Expected result "%s" processingTimeSeconds >= 0', $key));
        }
    }

    private function createClient(MockHandler $mockHandler): WebhookClient
    {
        $stack = HandlerStack::create($mockHandler);
        $stack->push(new AuthMiddleware('6.7.0', $this->createMock(AppLocaleProvider::class)));
        $guzzle = new Client(['handler' => $stack]);

        return new WebhookClient($guzzle, new NativeClock());
    }

    /**
     * @param array<string, string> $headers
     */
    private function createWebhookRequest(
        string $url = 'https://example.com/webhook',
        ?string $secret = 'test-secret',
        array $headers = [],
    ): WebhookRequest {
        $payload = json_encode(['data' => 'payload', 'timestamp' => time()], \JSON_THROW_ON_ERROR);

        $allHeaders = array_merge([
            'Content-Type' => 'application/json',
            'sw-version' => '6.7.0',
            AuthMiddleware::SHOPWARE_CONTEXT_LANGUAGE => 'en-GB',
            AuthMiddleware::SHOPWARE_USER_LANGUAGE => 'en-GB',
        ], $headers);

        $request = new Request('POST', $url, $allHeaders, $payload);

        $options = ['connect_timeout' => 10, 'timeout' => 20];
        if ($secret !== null) {
            $options[AuthMiddleware::APP_REQUEST_TYPE] = [AuthMiddleware::APP_SECRET => $secret];
        }

        return new WebhookRequest($request, $allHeaders, $payload, time(), $options);
    }

    private function assertRequestHasCorrectHeaders(RequestInterface $request): void
    {
        static::assertSame('application/json', $request->getHeaderLine('Content-Type'));
        static::assertSame('6.7.0', $request->getHeaderLine('sw-version'));
    }

    private function assertRequestIsSigned(RequestInterface $request): void
    {
        static::assertTrue($request->hasHeader(RequestSigner::SHOPWARE_SHOP_SIGNATURE));

        $body = $request->getBody();
        $payload = $body->getContents();
        $body->rewind();

        $expectedSignature = hash_hmac('sha256', $payload, 'test-secret');

        static::assertSame($expectedSignature, $request->getHeaderLine(RequestSigner::SHOPWARE_SHOP_SIGNATURE));
    }
}
