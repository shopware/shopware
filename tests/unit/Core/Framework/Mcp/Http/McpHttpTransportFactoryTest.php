<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp\Http;

use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Shopware\Core\Framework\Log\Package;
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
#[Package('framework')]
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

    /**
     * A parameterless tool encodes its empty properties map as `[]`, but JSON Schema requires an
     * object there. Strict clients reject the whole payload over it — OpenAI answers
     * `400 invalid_function_parameters: "[] is not of type 'object'"` — and because
     * shopware-toolsets-list is advertised in every session, that breaks every request such a
     * client makes, not just calls to that tool.
     */
    public function testCreateResponseForcesEmptyToolPropertiesToAnObject(): void
    {
        $json = '{"jsonrpc":"2.0","id":1,"result":{"tools":[{"name":"shopware-toolsets-list","inputSchema":{"type":"object","properties":[]}}]}}';

        $response = $this->factory()->createResponse($this->psrResponse('application/json', $json));

        static::assertStringContainsString('"properties":{}', (string) $response->getContent());
        static::assertStringNotContainsString('"properties":[]', (string) $response->getContent());
    }

    public function testCreateResponseForcesEmptyToolPropertiesInsideAnEventStreamBatch(): void
    {
        // What tools/list looks like right after a toolset-enable: the list_changed notification
        // is batched alongside the response, so the tools travel the SSE path instead.
        $json = '[{"jsonrpc":"2.0","method":"notifications/tools/list_changed"},'
            . '{"jsonrpc":"2.0","id":2,"result":{"tools":[{"name":"shopware-toolsets-list","inputSchema":{"type":"object","properties":[]}}]}}]';

        $response = $this->factory()->createResponse($this->psrResponse('application/json', $json));

        static::assertStringStartsWith('text/event-stream', (string) $response->headers->get('Content-Type'));
        static::assertStringContainsString('"properties":{}', (string) $response->getContent());
        static::assertStringNotContainsString('"properties":[]', (string) $response->getContent());
    }

    public function testCreateResponseForcesEmptyToolPropertiesInsideANativeEventStream(): void
    {
        // The SDK can answer a tools/list directly as an SSE stream when the client accepts
        // text/event-stream (the shape right after a toolset-enable). That skips the JSON branch,
        // so the `data:` frame must still be normalized while the SSE framing (event:/id:) stays
        // intact for resumability.
        $data = '{"jsonrpc":"2.0","id":2,"result":{"tools":[{"name":"swag-dev-tools-list-skills","inputSchema":{"type":"object","properties":[]}}]}}';
        $sse = "event: message\nid: 1\ndata: " . $data . "\n\n";

        $response = $this->factory()->createResponse($this->psrResponse('text/event-stream', $sse));

        static::assertStringStartsWith('text/event-stream', (string) $response->headers->get('Content-Type'));

        $body = (string) $response->getContent();
        static::assertStringContainsString('"properties":{}', $body);
        static::assertStringNotContainsString('"properties":[]', $body);
        static::assertStringContainsString("event: message\nid: 1\ndata: ", $body, 'SSE framing must be preserved');
    }

    public function testCreateResponseLeavesScalarEventStreamDataFramesUntouched(): void
    {
        // A `data:` frame whose payload is a JSON scalar (not an object/array) cannot carry a tool
        // list, so it must be passed through verbatim while a sibling tools/list frame is normalized.
        $tools = '{"jsonrpc":"2.0","id":2,"result":{"tools":[{"name":"t","inputSchema":{"type":"object","properties":[]}}]}}';
        $sse = "event: message\ndata: 42\n\nevent: message\ndata: " . $tools . "\n\n";

        $response = $this->factory()->createResponse($this->psrResponse('text/event-stream', $sse));

        $body = (string) $response->getContent();
        static::assertStringContainsString('data: 42', $body, 'scalar data frame must be preserved verbatim');
        static::assertStringContainsString('"properties":{}', $body);
        static::assertStringNotContainsString('"properties":[]', $body);
    }

    public function testCreateResponseForcesEmptyNestedObjectPropertiesToAnObject(): void
    {
        // A nested object parameter with no members hits the same `[]` vs `{}` problem, one level
        // deeper than the tool's top-level input schema.
        $json = '{"jsonrpc":"2.0","id":1,"result":{"tools":[{"name":"t","inputSchema":{"type":"object","properties":{"filter":{"type":"object","properties":[]}}}}]}}';

        $response = $this->factory()->createResponse($this->psrResponse('application/json', $json));

        static::assertStringContainsString('"properties":{}', (string) $response->getContent());
        static::assertStringNotContainsString('"properties":[]', (string) $response->getContent());
    }

    public function testCreateResponseForcesEmptyOutputSchemaPropertiesToAnObject(): void
    {
        $json = '{"jsonrpc":"2.0","id":1,"result":{"tools":[{"name":"t","inputSchema":{"type":"object","properties":{"q":{"type":"string"}}},"outputSchema":{"type":"object","properties":[]}}]}}';

        $response = $this->factory()->createResponse($this->psrResponse('application/json', $json));

        static::assertStringContainsString('"properties":{}', (string) $response->getContent());
        static::assertStringNotContainsString('"properties":[]', (string) $response->getContent());
    }

    public function testCreateResponsePreservesResponseHeadersWhenRewritingTheBody(): void
    {
        // Rewriting the body must not drop headers the SDK set; the untouched path keeps them all,
        // so the normalized path has to as well (including the Content-Type charset).
        $json = '{"jsonrpc":"2.0","id":1,"result":{"tools":[{"name":"t","inputSchema":{"type":"object","properties":[]}}]}}';

        $psrResponse = $this->psrResponse('application/json; charset=utf-8', $json)
            ->withHeader('X-Custom-Header', 'kept');

        $response = $this->factory()->createResponse($psrResponse);

        static::assertSame('kept', $response->headers->get('X-Custom-Header'));
        static::assertStringContainsString('charset=utf-8', (string) $response->headers->get('Content-Type'));
    }

    public function testCreateResponseLeavesPopulatedToolPropertiesUntouched(): void
    {
        $json = '{"jsonrpc":"2.0","id":1,"result":{"tools":[{"name":"t","inputSchema":{"type":"object","properties":{"q":{"type":"string"}}}}]}}';

        $response = $this->factory()->createResponse($this->psrResponse('application/json', $json));

        static::assertSame($json, $response->getContent());
    }

    public function testCreateResponseKeepsTheSessionHeaderWhenRewritingTheBody(): void
    {
        $json = '{"jsonrpc":"2.0","id":1,"result":{"tools":[{"name":"t","inputSchema":{"type":"object","properties":[]}}]}}';

        $psrResponse = $this->psrResponse('application/json', $json)
            ->withHeader(PlatformRequest::HEADER_MCP_SESSION_ID, 'session-123');

        $response = $this->factory()->createResponse($psrResponse);

        static::assertSame('session-123', $response->headers->get(PlatformRequest::HEADER_MCP_SESSION_ID));
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
