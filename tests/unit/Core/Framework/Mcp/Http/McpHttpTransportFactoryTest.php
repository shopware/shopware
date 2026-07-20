<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp\Http;

use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Shopware\Core\Framework\Mcp\Http\McpHttpTransportFactory;
use Shopware\Core\Framework\Mcp\McpAllowedHostsProvider;
use Shopware\Core\PlatformRequest;
use Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory;
use Symfony\Bridge\PsrHttpMessage\HttpFoundationFactoryInterface;
use Symfony\Bridge\PsrHttpMessage\HttpMessageFactoryInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(McpHttpTransportFactory::class)]
class McpHttpTransportFactoryTest extends TestCase
{
    public function testIsAvailableRequiresAllBundleFactories(): void
    {
        $psr17 = new Psr17Factory();
        $hosts = static::createStub(McpAllowedHostsProvider::class);
        $message = static::createStub(HttpMessageFactoryInterface::class);
        $foundation = static::createStub(HttpFoundationFactoryInterface::class);

        static::assertTrue((new McpHttpTransportFactory($message, $psr17, $psr17, $foundation, $hosts))->isAvailable());
        static::assertFalse((new McpHttpTransportFactory(null, $psr17, $psr17, $foundation, $hosts))->isAvailable());
        static::assertFalse((new McpHttpTransportFactory($message, null, $psr17, $foundation, $hosts))->isAvailable());
        static::assertFalse((new McpHttpTransportFactory($message, $psr17, null, $foundation, $hosts))->isAvailable());
        static::assertFalse((new McpHttpTransportFactory($message, $psr17, $psr17, null, $hosts))->isAvailable());
    }

    public function testCreateTransportBuildsTransportWithoutError(): void
    {
        // The return type guarantees the concrete transport; this exercises the middleware wiring
        // (seeding the DNS-rebinding protection with the allowed hosts) without error.
        $this->expectNotToPerformAssertions();

        $this->factory()->createTransport(new Request());
    }

    public function testCreateStreamReturnsStreamWithGivenContent(): void
    {
        static::assertSame('payload', (string) $this->factory()->createStream('payload'));
    }

    public function testCreateResponseKeepsSingleJsonObjectAsApplicationJson(): void
    {
        $json = '{"jsonrpc":"2.0","id":1,"result":{}}';

        $response = $this->factory()->createResponse($this->psrResponse('application/json', $json));

        static::assertStringStartsWith('application/json', (string) $response->headers->get('Content-Type'));
        static::assertSame($json, $response->getContent());
    }

    public function testCreateResponseConvertsJsonRpcBatchArrayToEventStream(): void
    {
        $batch = '[{"jsonrpc":"2.0","method":"notifications/tools/list_changed"},{"jsonrpc":"2.0","id":2,"result":{}}]';

        $response = $this->factory()->createResponse(
            $this->psrResponse('application/json', $batch)->withHeader(PlatformRequest::HEADER_MCP_SESSION_ID, 'session-1'),
        );

        static::assertStringStartsWith('text/event-stream', (string) $response->headers->get('Content-Type'));
        static::assertSame('session-1', $response->headers->get(PlatformRequest::HEADER_MCP_SESSION_ID));

        $body = (string) $response->getContent();
        static::assertSame(2, substr_count($body, 'event: message'), 'each JSON-RPC message must be its own SSE event');
        static::assertStringContainsString('data: {"jsonrpc":"2.0","method":"notifications\/tools\/list_changed"}', $body);
    }

    public function testCreateResponsePassesThroughAnEventStream(): void
    {
        $response = $this->factory()->createResponse($this->psrResponse('text/event-stream', "event: message\ndata: {}\n\n"));

        static::assertStringStartsWith('text/event-stream', (string) $response->headers->get('Content-Type'));
    }

    private function psrResponse(string $contentType, string $body): ResponseInterface
    {
        $psr17 = new Psr17Factory();

        return $psr17->createResponse(200)
            ->withHeader('Content-Type', $contentType)
            ->withBody($psr17->createStream($body));
    }

    private function factory(): McpHttpTransportFactory
    {
        $psr17 = new Psr17Factory();

        $httpMessageFactory = static::createStub(HttpMessageFactoryInterface::class);
        $httpMessageFactory->method('createRequest')->willReturn($psr17->createServerRequest('GET', '/api/_mcp'));

        return new McpHttpTransportFactory(
            $httpMessageFactory,
            $psr17,
            $psr17,
            new HttpFoundationFactory(),
            static::createStub(McpAllowedHostsProvider::class),
        );
    }
}
