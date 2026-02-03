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
use Shopware\Core\Framework\App\Hmac\Guzzle\AuthMiddleware;
use Shopware\Core\Framework\App\Hmac\RequestSigner;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Webhook\Exception\WebhookSendException;
use Shopware\Core\Framework\Webhook\Message\WebhookEventMessage;
use Shopware\Core\Framework\Webhook\Service\WebhookClient;

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
        $message = $this->createMessage();

        $result = $client->send($message);

        static::assertSame(200, $result['statusCode']);
        static::assertSame('OK', $result['reasonPhrase']);
        static::assertSame($responseBody, $result['body']);
        static::assertArrayHasKey('X-Response-Header', $result['headers']);

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

        $client->send($message);

        $request = $mockHandler->getLastRequest();
        static::assertInstanceOf(RequestInterface::class, $request);
        static::assertSame('custom-value', $request->getHeaderLine('X-Custom'));
        static::assertSame('another', $request->getHeaderLine('X-Another'));
    }

    public function testSendThrowsExceptionOnHttpError(): void
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
        $message = $this->createMessage();

        try {
            $client->send($message);
            static::fail('Expected WebhookSendException was not thrown');
        } catch (WebhookSendException $e) {
            static::assertTrue($e->hasResponse());
            static::assertSame(400, $e->getResponseStatusCode());
            static::assertSame('Bad Request', $e->getResponseReasonPhrase());
            static::assertSame($errorBody, $e->getResponseBody());
            static::assertNotNull($e->getResponseHeaders());
        }
    }

    public function testSendThrowsExceptionOnNetworkError(): void
    {
        $mockHandler = new MockHandler([
            new ConnectException('Connection refused', new Request('POST', 'https://example.com')),
        ]);

        $client = $this->createClient($mockHandler);
        $message = $this->createMessage();

        try {
            $client->send($message);
            static::fail('Expected WebhookSendException was not thrown');
        } catch (WebhookSendException $e) {
            static::assertFalse($e->hasResponse());
            static::assertNull($e->getResponseStatusCode());
            static::assertStringContainsString('Connection refused', $e->getMessage());
        }
    }

    public function testSendWithoutSecret(): void
    {
        $mockHandler = new MockHandler([new Response(200)]);
        $client = $this->createClient($mockHandler);

        $message = $this->createMessage(secret: null);

        $client->send($message);

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
        $handlerStack->push($historyMiddleware);
        $guzzle = new Client(['handler' => $handlerStack]);
        $client = new WebhookClient($guzzle);

        $messages = [
            $this->createMessage(url: 'https://example.com/hook1', webhookHeaders: ['X-Custom' => 'value1']),
            $this->createMessage(url: 'https://example.com/hook2'),
            $this->createMessage(url: 'https://example.com/hook3', secret: null),
        ];

        $client->sendBatch($messages);

        static::assertIsArray($history);
        static::assertCount(3, $history);

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

    private function createClient(MockHandler $mockHandler): WebhookClient
    {
        $guzzle = new Client(['handler' => HandlerStack::create($mockHandler)]);

        return new WebhookClient($guzzle);
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
        static::assertArrayHasKey('timestamp', $payload);
        static::assertIsInt($payload['timestamp']);
    }
}
