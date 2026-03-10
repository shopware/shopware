<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp\Loader;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
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

        $body = json_decode($lastRequest->getBody()->getContents(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame('sync-orders', $body['tool']);
        static::assertSame(['foo' => 'bar'], $body['arguments']);
        static::assertSame('https://shop.example.com', $body['source']['url']);
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

    public function testResponseWithoutSuccessKeyLogsWarning(): void
    {
        $mock = new MockHandler();
        $mock->append(new Response(200, [], '{"result":"ok"}'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('info');
        $logger->expects($this->once())->method('warning')
            ->with(static::stringContains('missing "success" key'));

        $executor = new AppMcpToolExecutor(
            new Client(['handler' => HandlerStack::create($mock)]),
            'https://shop.example.com',
            30,
            $logger,
        );

        $executor->execute('my-tool', 'secret', 'https://example.com', []);
    }

    public function testExceptionWithLoggerLogsError(): void
    {
        $mock = new MockHandler();
        $mock->append(new \RuntimeException('timeout'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('error')
            ->with(static::stringContains('execution failed'));

        $executor = new AppMcpToolExecutor(
            new Client(['handler' => HandlerStack::create($mock)]),
            'https://shop.example.com',
            30,
            $logger,
        );

        $result = $executor->execute('my-tool', 'secret', 'https://example.com', []);
        $data = json_decode($result, true, 512, \JSON_THROW_ON_ERROR);
        static::assertFalse($data['success']);
    }

    public function testSuccessResponseWithSuccessKeyDoesNotLogWarning(): void
    {
        $mock = new MockHandler();
        $mock->append(new Response(200, [], '{"success":true,"data":{}}'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('info');
        $logger->expects($this->never())->method('warning');

        $executor = new AppMcpToolExecutor(
            new Client(['handler' => HandlerStack::create($mock)]),
            'https://shop.example.com',
            30,
            $logger,
        );

        $executor->execute('my-tool', 'secret', 'https://example.com', []);
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
