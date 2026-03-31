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
use Shopware\Core\Defaults;
use Shopware\Core\Framework\App\AppLocaleProvider;
use Shopware\Core\Framework\App\Hmac\Guzzle\AuthMiddleware;
use Shopware\Core\Framework\App\Hmac\RequestSigner;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Webhook\Message\WebhookEventMessage;
use Shopware\Core\Framework\Webhook\Service\WebhookClient;
use Shopware\Core\Framework\Webhook\Service\WebhookRequest;
use Shopware\Core\Framework\Webhook\Service\WebhookResult;
use Symfony\Component\Clock\MockClock;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(WebhookClient::class)]
#[CoversClass(WebhookRequest::class)]
#[CoversClass(WebhookResult::class)]
class WebhookClientTest extends TestCase
{
    private MockClock $clock;

    private int $createdTimestamp;

    protected function setUp(): void
    {
        $this->clock = new MockClock();
        $this->createdTimestamp = $this->clock->now()->modify('-1 minute')->getTimestamp();
    }

    public function testSendSuccessful(): void
    {
        $responseBody = ['status' => 'ok'];
        $mockHandler = new MockHandler([
            new Response(200, ['X-Response-Header' => 'value'], json_encode($responseBody, \JSON_THROW_ON_ERROR)),
        ]);

        $client = $this->createClient($mockHandler);
        $message = $this->createMessage();

        $result = $client->send($client->createRequest($message));

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
        $mockHandler = new MockHandler([new Response(200)]);
        $client = $this->createClient($mockHandler);

        $customHeaders = ['X-Custom' => 'custom-value', 'X-Another' => 'another'];
        $message = $this->createMessage(webhookHeaders: $customHeaders);

        $client->send($client->createRequest($message));

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
        $result = $client->send($client->createRequest($this->createMessage()));

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
        $result = $client->send($client->createRequest($this->createMessage()));

        static::assertFalse($result->successful());
        static::assertFalse($result->hasResponse());
        static::assertNull($result->statusCode);
        static::assertSame('Connection refused', $result->errorMessage);
    }

    public function testSendWithoutSecret(): void
    {
        $mockHandler = new MockHandler([new Response(200)]);
        $client = $this->createClient($mockHandler);

        $message = $this->createMessage(secret: null);

        $client->send($client->createRequest($message));

        $request = $mockHandler->getLastRequest();
        static::assertInstanceOf(RequestInterface::class, $request);
        static::assertFalse($request->hasHeader(RequestSigner::SHOPWARE_SHOP_SIGNATURE));
    }

    public function testSendBatch(): void
    {
        $history = [];
        $historyMiddleware = Middleware::history($history);

        $mockHandler = new MockHandler([
            new Response(200),
            new Response(201),
            new Response(202),
        ]);

        $handlerStack = HandlerStack::create($mockHandler);
        $handlerStack->push(new AuthMiddleware('6.7.0', $this->createMock(AppLocaleProvider::class)));
        $handlerStack->push($historyMiddleware);
        $guzzle = new Client(['handler' => $handlerStack]);
        $client = new WebhookClient($guzzle, $this->clock);

        $requests = [
            'hook1' => $client->createRequest($this->createMessage(url: 'https://example.com/hook1', webhookHeaders: ['X-Custom' => 'value1'])),
            'hook2' => $client->createRequest($this->createMessage(url: 'https://example.com/hook2')),
            'hook3' => $client->createRequest($this->createMessage(url: 'https://example.com/hook3', secret: null)),
        ];

        $results = $client->sendBatch(...$requests);

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
        $this->assertRequestHasValidPayload($request1);

        // Assert second request
        $request2 = $history[1]['request'];
        static::assertInstanceOf(RequestInterface::class, $request2);
        static::assertSame('https://example.com/hook2', (string) $request2->getUri());
        static::assertSame('POST', $request2->getMethod());
        $this->assertRequestHasCorrectHeaders($request2);
        $this->assertRequestIsSigned($request2);
        $this->assertRequestHasValidPayload($request2);

        // Assert third request (no secret, should not be signed)
        $request3 = $history[2]['request'];
        static::assertInstanceOf(RequestInterface::class, $request3);
        static::assertSame('https://example.com/hook3', (string) $request3->getUri());
        static::assertSame('POST', $request3->getMethod());
        $this->assertRequestHasCorrectHeaders($request3);
        static::assertFalse($request3->hasHeader(RequestSigner::SHOPWARE_SHOP_SIGNATURE));
        $this->assertRequestHasValidPayload($request3);
    }

    public function testSendBatchReturnsFailureResults(): void
    {
        $history = [];
        $historyMiddleware = Middleware::history($history);

        $mockHandler = new MockHandler([
            new ConnectException('Connection refused', new Request('POST', 'https://example.com/hook1')),
            new Response(200),
        ]);

        $handlerStack = HandlerStack::create($mockHandler);
        $handlerStack->push(new AuthMiddleware('6.7.0', $this->createMock(AppLocaleProvider::class)));
        $handlerStack->push($historyMiddleware);

        $client = new WebhookClient(new Client(['handler' => $handlerStack]), $this->clock);

        $results = $client->sendBatch(...[
            'hook1' => $client->createRequest($this->createMessage(url: 'https://example.com/hook1')),
            'hook2' => $client->createRequest($this->createMessage(url: 'https://example.com/hook2')),
        ]);

        static::assertIsArray($history);
        static::assertCount(2, $history);
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

        // Should not throw
        static::assertSame([], $client->sendBatch());

        static::assertNull($mockHandler->getLastRequest());
    }

    public function testCreateRequestBuildsReusableRequestData(): void
    {
        $client = $this->createClient(new MockHandler([]));
        $message = $this->createMessage(webhookHeaders: ['X-Custom' => 'custom-value']);

        $webhookRequest = $client->createRequest($message);

        static::assertSame([
            'Content-Type' => 'application/json',
            'sw-version' => '6.7.0',
            'X-Custom' => 'custom-value',
            AuthMiddleware::SHOPWARE_CONTEXT_LANGUAGE => Defaults::LANGUAGE_SYSTEM,
            AuthMiddleware::SHOPWARE_USER_LANGUAGE => 'en-GB',
        ], $webhookRequest->headers);
        $body = $webhookRequest->request->getBody();
        static::assertSame($webhookRequest->body, $body->getContents());
        $body->rewind();
        static::assertSame('custom-value', $webhookRequest->request->getHeaderLine('X-Custom'));
        // Signing is deferred to send time via AuthMiddleware; the PSR-7 request itself is unsigned.
        static::assertFalse($webhookRequest->request->hasHeader(RequestSigner::SHOPWARE_SHOP_SIGNATURE));
        static::assertSame('test-secret', $webhookRequest->secret);
        $this->assertRequestHasValidPayload($webhookRequest->request);

        // timestamp on WebhookRequest must match the payload timestamp so that the event log
        // and the HTTP payload always reference the same instant (no two-clock-call drift).
        $payload = json_decode($webhookRequest->body, true, flags: \JSON_THROW_ON_ERROR);
        static::assertSame($webhookRequest->timestamp, $payload['timestamp']);
    }

    private function createClient(MockHandler $mockHandler): WebhookClient
    {
        $stack = HandlerStack::create($mockHandler);
        $stack->push(new AuthMiddleware('6.7.0', $this->createMock(AppLocaleProvider::class)));
        $guzzle = new Client(['handler' => $stack]);

        return new WebhookClient($guzzle, $this->clock);
    }

    /**
     * @param array<string, string> $webhookHeaders
     */
    private function createMessage(
        string $url = 'https://example.com/webhook',
        ?string $secret = 'test-secret',
        array $webhookHeaders = [],
    ): WebhookEventMessage {
        return new WebhookEventMessage(
            Uuid::randomHex(),
            ['data' => 'payload'],
            Uuid::randomHex(),
            Uuid::randomHex(),
            '6.7.0',
            $url,
            $secret,
            Defaults::LANGUAGE_SYSTEM,
            'en-GB',
            $this->createdTimestamp,
            $webhookHeaders
        );
    }

    private function assertRequestHasCorrectHeaders(RequestInterface $request): void
    {
        static::assertSame('application/json', $request->getHeaderLine('Content-Type'));
        static::assertSame('6.7.0', $request->getHeaderLine('sw-version'));
        static::assertSame(Defaults::LANGUAGE_SYSTEM, $request->getHeaderLine(AuthMiddleware::SHOPWARE_CONTEXT_LANGUAGE));
        static::assertSame('en-GB', $request->getHeaderLine(AuthMiddleware::SHOPWARE_USER_LANGUAGE));
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

    private function assertRequestHasValidPayload(RequestInterface $request): void
    {
        $body = $request->getBody();
        $contents = $body->getContents();
        $body->rewind();

        $payload = json_decode($contents, true, flags: \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('data', $payload);
        static::assertSame('payload', $payload['data']);
        static::assertSame($this->createdTimestamp, $payload['createdTimestamp']);
        static::assertArrayHasKey('timestamp', $payload);
        static::assertIsInt($payload['timestamp']);
    }
}
