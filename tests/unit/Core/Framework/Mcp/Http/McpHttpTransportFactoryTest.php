<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp\Http;

use Doctrine\DBAL\Connection;
use Mcp\Server;
use Mcp\Server\Transport\CallbackStream;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Http\McpHttpTransportFactory;
use Shopware\Core\Framework\Mcp\McpAllowedHostsProvider;
use Shopware\Core\PlatformRequest;
use Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Bridge\PsrHttpMessage\HttpFoundationFactoryInterface;
use Symfony\Bridge\PsrHttpMessage\HttpMessageFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

    /**
     * @return iterable<string, array{string, string|null, list<string>}>
     */
    public static function acceptedRequestProvider(): iterable
    {
        yield 'a CLI client sends no Origin header' => ['https://shop.example/api/_mcp', null, []];
        yield 'a browser client on the shop origin' => ['https://shop.example/api/_mcp', 'https://shop.example', []];
        yield 'a browser client on another sales channel domain' => ['https://shop.example/api/_mcp', 'https://de.shop.example', []];
        yield 'the MCP Inspector against a local shop' => ['http://localhost:8000/api/_mcp', 'http://localhost:6274', []];
        yield 'the MCP Inspector against a .localhost shop' => ['http://trunk.localhost:8088/api/_mcp', 'http://localhost:6274', []];
        yield 'a configured exact origin' => ['https://shop.example/api/_mcp', 'https://chat.example', ['https://chat.example']];
        yield 'a configured origin with any port' => ['https://shop.example/api/_mcp', 'http://localhost:6274', ['http://localhost:*']];
        yield 'any origin when configured' => ['https://shop.example/api/_mcp', 'https://attacker.example', ['*']];
        yield 'a request to a local host' => ['http://localhost:8000/api/_mcp', null, []];
    }

    /**
     * @param list<string> $configuredOrigins
     */
    #[DataProvider('acceptedRequestProvider')]
    public function testTransportAcceptsRequestsForAllowedHostsAndOrigins(string $uri, ?string $origin, array $configuredOrigins): void
    {
        $response = $this->initialize($uri, $origin, $configuredOrigins);

        static::assertSame(200, $response->getStatusCode(), (string) $response->getBody());
        static::assertNotSame('', $response->getHeaderLine(PlatformRequest::HEADER_MCP_SESSION_ID));
    }

    /**
     * @return iterable<string, array{string, string|null, list<string>, string}>
     */
    public static function rejectedRequestProvider(): iterable
    {
        yield 'a host the shop does not serve' => ['https://rebound.example/api/_mcp', null, [], 'Forbidden: Invalid Host header.'];
        yield 'an origin on a foreign host' => ['https://shop.example/api/_mcp', 'https://attacker.example', [], 'Forbidden: Invalid Origin header.'];
        yield 'an opaque origin' => ['https://shop.example/api/_mcp', 'null', [], 'Forbidden: Invalid Origin header.'];
        yield 'another scheme and port of a shop host' => ['https://shop.example/api/_mcp', 'http://shop.example:8080', [], 'Forbidden: Invalid Origin header.'];
        yield 'the MCP Inspector against a public shop' => ['https://shop.example/api/_mcp', 'http://localhost:6274', [], 'Forbidden: Invalid Origin header.'];
        yield 'another port than the configured one' => ['https://shop.example/api/_mcp', 'https://chat.example:8443', ['https://chat.example'], 'Forbidden: Invalid Origin header.'];
    }

    /**
     * @param list<string> $configuredOrigins
     */
    #[DataProvider('rejectedRequestProvider')]
    public function testTransportRejectsRequestsForForeignHostsAndOrigins(string $uri, ?string $origin, array $configuredOrigins, string $message): void
    {
        $response = $this->initialize($uri, $origin, $configuredOrigins);

        static::assertSame(403, $response->getStatusCode());
        static::assertSame($message, (string) $response->getBody());
    }

    public function testLogsARejectedOrigin(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('warning')->with(
            static::stringContains('shopware.mcp.allowed_origins'),
            ['origin' => 'https://attacker.example', 'host' => 'shop.example'],
        );

        $this->initialize('https://shop.example/api/_mcp', 'https://attacker.example', [], $logger);
    }

    public function testDoesNotLogASameOriginRequest(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('warning');

        $this->initialize('https://shop.example/api/_mcp', 'https://shop.example', [], $logger);
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

    public function testCreateResponsePassesThroughAnEventStreamVerbatim(): void
    {
        $sse = "event: message\nid: 1\ndata: {\"jsonrpc\":\"2.0\",\"id\":2,\"result\":{\"tools\":[{\"name\":\"t\",\"inputSchema\":{\"type\":\"object\",\"properties\":{}}}]}}\n\n";

        $response = $this->factory()->createResponse($this->psrResponse('text/event-stream', $sse));

        static::assertStringStartsWith('text/event-stream', (string) $response->headers->get('Content-Type'));
        static::assertSame($sse, $this->sentContent($response));
    }

    public function testCreateResponseLeavesTheSdkEventStreamUnreadUntilItIsSent(): void
    {
        $psr17 = new Psr17Factory();
        $frame = "event: message\ndata: {\"jsonrpc\":\"2.0\",\"method\":\"notifications/progress\"}\n\n";
        $stream = new CallbackStream(static function () use ($frame): void {
            echo $frame;
        });

        $response = $this->factory()->createResponse(
            $psr17->createResponse(200)
                ->withHeader('Content-Type', 'text/event-stream')
                ->withHeader(PlatformRequest::HEADER_MCP_SESSION_ID, 'session-1')
                ->withBody($stream),
        );

        static::assertFalse($stream->eof(), 'the callback must not run before the headers are sent');
        static::assertInstanceOf(StreamedResponse::class, $response);
        static::assertStringStartsWith('text/event-stream', (string) $response->headers->get('Content-Type'));
        static::assertSame('session-1', $response->headers->get(PlatformRequest::HEADER_MCP_SESSION_ID));

        static::assertSame($frame, $this->sentContent($response));
    }

    /**
     * A parameterless tool has an empty `properties` object. JSON Schema requires an object there,
     * and strict clients reject the whole tools/list if it arrives as `[]`, for example OpenAI with
     * `400 invalid_function_parameters: "[] is not of type 'object'"`. The SDK already emits `{}`;
     * the transport must not collapse it while passing the single object through.
     */
    public function testCreateResponseKeepsEmptyToolPropertiesOfASingleJsonObject(): void
    {
        $json = '{"jsonrpc":"2.0","id":1,"result":{"tools":[{"name":"shopware-toolsets-list","inputSchema":{"type":"object","properties":{}}}]}}';

        $response = $this->factory()->createResponse($this->psrResponse('application/json; charset=utf-8', $json)->withHeader('X-Custom-Header', 'kept'));

        static::assertSame($json, $response->getContent());
        static::assertSame('kept', $response->headers->get('X-Custom-Header'));
    }

    private function sentContent(Response $response): string
    {
        ob_start();
        $response->sendContent();

        return (string) ob_get_clean();
    }

    private function psrResponse(string $contentType, string $body): ResponseInterface
    {
        $psr17 = new Psr17Factory();

        return $psr17->createResponse(200)
            ->withHeader('Content-Type', $contentType)
            ->withBody($psr17->createStream($body));
    }

    /**
     * @param list<string> $configuredOrigins
     */
    private function initialize(string $uri, ?string $origin, array $configuredOrigins = [], ?LoggerInterface $logger = null): ResponseInterface
    {
        $psr17 = new Psr17Factory();
        $connection = static::createStub(Connection::class);
        $connection->method('fetchFirstColumn')->willReturn(['https://shop.example', 'https://de.shop.example', 'http://trunk.localhost:8088']);

        $factory = new McpHttpTransportFactory(
            new PsrHttpFactory($psr17, $psr17, $psr17, $psr17),
            $psr17,
            $psr17,
            new HttpFoundationFactory(),
            new McpAllowedHostsProvider($connection, 'https://shop.example', $configuredOrigins),
            $logger,
        );

        $request = Request::create($uri, Request::METHOD_POST, server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json, text/event-stream',
        ], content: json_encode([
            'jsonrpc' => '2.0',
            'method' => 'initialize',
            'params' => [
                'protocolVersion' => '2025-06-18',
                'capabilities' => new \stdClass(),
                'clientInfo' => ['name' => 'test', 'version' => '1.0'],
            ],
            'id' => 1,
        ], \JSON_THROW_ON_ERROR));

        if ($origin !== null) {
            $request->headers->set('Origin', $origin);
        }

        return Server::builder()->build()->run($factory->createTransport($request));
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
