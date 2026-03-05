<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp\Loader;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Hmac\RequestSigner;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Loader\AppMcpToolExecutor;

/**
 * @internal
 */
#[CoversClass(AppMcpToolExecutor::class)]
#[Package('framework')]
class AppMcpToolExecutorTest extends TestCase
{
    private MockHandler $mockHandler;

    private AppMcpToolExecutor $executor;

    protected function setUp(): void
    {
        $this->mockHandler = new MockHandler();
        $client = new Client(['handler' => HandlerStack::create($this->mockHandler)]);
        $this->executor = new AppMcpToolExecutor(
            $client,
            'https://shop.example.com',
            30,
        );
    }

    public function testSuccessfulExecutionReturnsResponseBody(): void
    {
        $expectedBody = '{"result":"ok"}';
        $this->mockHandler->append(new Response(200, [], $expectedBody));

        $result = $this->executor->execute(
            'sync-orders',
            'test-secret',
            'https://app.example.com/mcp/sync',
            ['foo' => 'bar'],
        );

        static::assertSame($expectedBody, $result);

        $lastRequest = $this->mockHandler->getLastRequest();
        static::assertNotNull($lastRequest);
        static::assertSame('POST', $lastRequest->getMethod());
        static::assertSame('https://app.example.com/mcp/sync', (string) $lastRequest->getUri());
        static::assertSame('application/json', $lastRequest->getHeaderLine('Content-Type'));
        static::assertSame('application/json', $lastRequest->getHeaderLine('Accept'));
        static::assertNotEmpty($lastRequest->getHeaderLine(RequestSigner::SHOPWARE_SHOP_SIGNATURE));

        $body = $lastRequest->getBody()->getContents();
        static::assertStringContainsString('"tool":"sync-orders"', $body);
        static::assertStringContainsString('"arguments":{"foo":"bar"}', $body);
        static::assertStringContainsString('"source":{"url":"https://shop.example.com"}', $body);
    }

    public function testFailedExecutionReturnsJsonError(): void
    {
        $this->mockHandler->append(new \RuntimeException('Connection refused'));

        $result = $this->executor->execute(
            'sync-orders',
            'test-secret',
            'https://app.example.com/mcp/sync',
            [],
        );

        $decoded = json_decode($result, true);
        static::assertIsArray($decoded);
        static::assertFalse($decoded['success']);
        static::assertStringContainsString('sync-orders', $decoded['error']);
        static::assertStringContainsString('Connection refused', $decoded['error']);
    }

    public function testHmacSignatureIsSentInHeader(): void
    {
        $this->mockHandler->append(new Response(200, [], '{}'));

        $this->executor->execute('my-tool', 'secret', 'https://example.com', []);

        $lastRequest = $this->mockHandler->getLastRequest();
        static::assertNotNull($lastRequest);
        $signature = $lastRequest->getHeaderLine(RequestSigner::SHOPWARE_SHOP_SIGNATURE);
        static::assertNotEmpty($signature);
        static::assertSame(64, \strlen($signature));
    }
}
