<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp\Controller;

use Mcp\Server;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Mcp\AllowList\McpAllowlistFilter;
use Shopware\Core\Framework\Mcp\AllowList\McpAllowlistProvider;
use Shopware\Core\Framework\Mcp\Controller\McpServerController;
use Shopware\Core\Framework\Mcp\McpException;
use Shopware\Core\Framework\RateLimiter\Exception\RateLimitExceededException;
use Shopware\Core\Framework\RateLimiter\RateLimiter;
use Shopware\Core\PlatformRequest;
use Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory;
use Symfony\Bridge\PsrHttpMessage\HttpFoundationFactoryInterface;
use Symfony\Bridge\PsrHttpMessage\HttpMessageFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[CoversClass(McpServerController::class)]
#[CoversClass(McpAllowlistFilter::class)]
class McpServerControllerTest extends TestCase
{
    private RateLimiter&MockObject $rateLimiter;

    private McpServerController $controller;

    protected function setUp(): void
    {
        $_SERVER['MCP_SERVER'] = '1';
        $this->rateLimiter = $this->createMock(RateLimiter::class);

        $this->controller = new McpServerController(
            Server::builder()->build(),
            static::createStub(HttpMessageFactoryInterface::class),
            static::createStub(HttpFoundationFactoryInterface::class),
            static::createStub(ResponseFactoryInterface::class),
            static::createStub(StreamFactoryInterface::class),
            $this->rateLimiter,
        );
    }

    protected function tearDown(): void
    {
        unset($_SERVER['MCP_SERVER']);
    }

    public function testHandleReturnsResponseForValidMcpRequest(): void
    {
        $body = json_encode([
            'jsonrpc' => '2.0',
            'method' => 'initialize',
            'params' => [
                'protocolVersion' => '2025-03-26',
                'capabilities' => new \stdClass(),
                'clientInfo' => ['name' => 'test', 'version' => '1.0'],
            ],
            'id' => 1,
        ], \JSON_THROW_ON_ERROR);

        $psrRequest = new ServerRequest('POST', '/api/_mcp', ['Content-Type' => 'application/json'], $body);
        $httpFoundationFactory = static::createStub(HttpFoundationFactoryInterface::class);
        $httpFoundationFactory->method('createResponse')->willReturn(new Response('', 200));

        $controller = $this->buildController($psrRequest, $httpFoundationFactory);
        $response = $controller->handle(new Request());

        static::assertSame(200, $response->getStatusCode());
    }

    public function testInitializeEnrichmentKeepsEmptyCapabilityObjects(): void
    {
        $body = json_encode([
            'jsonrpc' => '2.0',
            'method' => 'initialize',
            'params' => [
                'protocolVersion' => '2025-03-26',
                'capabilities' => new \stdClass(),
                'clientInfo' => ['name' => 'test', 'version' => '1.0'],
            ],
            'id' => 1,
        ], \JSON_THROW_ON_ERROR);

        $psrRequest = new ServerRequest('POST', '/api/_mcp', ['Content-Type' => 'application/json'], $body);
        $controller = $this->buildController($psrRequest, new HttpFoundationFactory());
        $sfRequest = Request::create('/api/_mcp', 'POST', content: $body);
        $sfRequest->attributes->set(
            PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT,
            Context::createDefaultContext(new AdminApiSource(null, 'integration-id')),
        );

        $response = $controller->handle($sfRequest);

        $data = json_decode((string) $response->getContent(), false, 512, \JSON_THROW_ON_ERROR);
        static::assertInstanceOf(\stdClass::class, $data);

        $result = $data->result ?? null;
        static::assertInstanceOf(\stdClass::class, $result);

        $capabilities = $result->capabilities ?? null;
        static::assertInstanceOf(\stdClass::class, $capabilities);
        static::assertInstanceOf(\stdClass::class, $capabilities->logging ?? null);
        static::assertInstanceOf(\stdClass::class, $capabilities->completions ?? null);

        $meta = $result->_meta ?? null;
        static::assertInstanceOf(\stdClass::class, $meta);

        $shopwareMeta = $meta->shopware ?? null;
        static::assertInstanceOf(\stdClass::class, $shopwareMeta);

        $integrationMeta = $shopwareMeta->integration ?? null;
        static::assertInstanceOf(\stdClass::class, $integrationMeta);
        static::assertSame('integration-id', $integrationMeta->id ?? null);
    }

    public function testHandleDetectsStreamedResponse(): void
    {
        $psrRequest = new ServerRequest('GET', '/api/_mcp');
        $httpFoundationFactory = $this->createMock(HttpFoundationFactoryInterface::class);
        $httpFoundationFactory->expects($this->once())
            ->method('createResponse')
            ->with(
                static::anything(),
                false,
            )
            ->willReturn(new Response('', 405));

        $controller = $this->buildController($psrRequest, $httpFoundationFactory);
        $response = $controller->handle(new Request());

        static::assertSame(405, $response->getStatusCode());
    }

    public function testPostWithMalformedJsonBodyDoesNotSetJsonRpcAttributeAndPassesThrough(): void
    {
        $psrRequest = new ServerRequest('POST', '/api/_mcp', ['Content-Type' => 'application/json'], 'not-json');
        $httpFoundationFactory = static::createStub(HttpFoundationFactoryInterface::class);
        $httpFoundationFactory->method('createResponse')->willReturn(new Response('', 400));

        $allowlistProvider = static::createStub(McpAllowlistProvider::class);
        $allowlistProvider->method('forCurrentRequest')->willReturn([
            'tools' => ['shopware-entity-search'],
            'resources' => null,
            'prompts' => null,
        ]);

        $controller = $this->buildController($psrRequest, $httpFoundationFactory, $allowlistProvider);
        $sfRequest = Request::create('/api/_mcp', 'POST', content: 'not-json');
        $response = $controller->handle($sfRequest);

        static::assertFalse($sfRequest->attributes->has(McpServerController::ATTRIBUTE_JSONRPC_BODY));
        static::assertSame(400, $response->getStatusCode());
    }

    /**
     * @return iterable<string, array{string, array{tools: list<string>|null, resources: list<string>|null, prompts: list<string>|null}}>
     */
    public static function allowedToolCallProvider(): iterable
    {
        yield 'tool explicitly in allowlist' => [
            'shopware-entity-search',
            ['tools' => ['shopware-entity-search'], 'resources' => null, 'prompts' => null],
        ];
        yield 'null tools allows all tools' => [
            'any-tool',
            ['tools' => null, 'resources' => null, 'prompts' => null],
        ];
    }

    /**
     * @param array{tools: list<string>|null, resources: list<string>|null, prompts: list<string>|null} $allowlist
     */
    #[DataProvider('allowedToolCallProvider')]
    public function testToolCallNotBlockedWhenAllowed(string $toolName, array $allowlist): void
    {
        $body = json_encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'tools/call',
            'params' => ['name' => $toolName, 'arguments' => []],
        ], \JSON_THROW_ON_ERROR);

        $psrRequest = new ServerRequest('POST', '/api/_mcp', ['Content-Type' => 'application/json'], $body);
        $httpFoundationFactory = static::createStub(HttpFoundationFactoryInterface::class);
        $httpFoundationFactory->method('createResponse')->willReturn(new Response('{}', 200));

        $allowlistProvider = static::createStub(McpAllowlistProvider::class);
        $allowlistProvider->method('forCurrentRequest')->willReturn($allowlist);

        $controller = $this->buildController($psrRequest, $httpFoundationFactory, $allowlistProvider);
        $sfRequest = Request::create('/api/_mcp', 'POST', content: $body);
        $response = $controller->handle($sfRequest);

        static::assertStringNotContainsString('allowlist', (string) $response->getContent());
    }

    /**
     * @return iterable<string, array{array{tools: list<string>|null, resources: list<string>|null, prompts: list<string>|null}, list<string>}>
     */
    public static function toolsListFilterProvider(): iterable
    {
        yield 'restricted allowlist shows only allowed tool' => [
            ['tools' => ['tool-a'], 'resources' => null, 'prompts' => null],
            ['tool-a'],
        ];
        yield 'null tools allowlist shows all tools' => [
            ['tools' => null, 'resources' => null, 'prompts' => null],
            ['tool-a', 'tool-b'],
        ];
        yield 'empty tools allowlist hides all tools' => [
            ['tools' => [], 'resources' => null, 'prompts' => null],
            [],
        ];
    }

    /**
     * @param array{tools: list<string>|null, resources: list<string>|null, prompts: list<string>|null} $allowlist
     * @param list<string> $expectedToolNames
     */
    #[DataProvider('toolsListFilterProvider')]
    public function testToolsListIsFilteredByAllowlist(array $allowlist, array $expectedToolNames): void
    {
        $server = Server::builder()
            ->addTool(static fn (): string => '[]', name: 'tool-a', description: 'Tool A')
            ->addTool(static fn (): string => '[]', name: 'tool-b', description: 'Tool B')
            ->build();

        $sessionId = $this->initializeMcpSession($server);

        $listBody = json_encode([
            'jsonrpc' => '2.0',
            'id' => 2,
            'method' => 'tools/list',
            'params' => [],
        ], \JSON_THROW_ON_ERROR);

        $allowlistProvider = static::createStub(McpAllowlistProvider::class);
        $allowlistProvider->method('forCurrentRequest')->willReturn($allowlist);

        $psrRequest = new ServerRequest(
            'POST',
            '/api/_mcp',
            ['Content-Type' => 'application/json', 'Mcp-Session-Id' => $sessionId],
            $listBody,
        );

        $controller = $this->buildController($psrRequest, new HttpFoundationFactory(), $allowlistProvider, server: $server);
        $sfRequest = Request::create('/api/_mcp', 'POST', content: $listBody, server: ['HTTP_MCP_SESSION_ID' => $sessionId]);
        $response = $controller->handle($sfRequest);

        $data = json_decode((string) $response->getContent(), true);
        $toolNames = array_column($data['result']['tools'] ?? [], 'name');
        static::assertSame($expectedToolNames, array_values($toolNames));
    }

    /**
     * @return iterable<string, array{?string, ?string, string}>
     */
    public static function rateLimitKeyProvider(): iterable
    {
        yield 'rate-limited by IP' => ['192.168.1.1', null, '192.168.1.1'];
        yield 'rate-limited by OAuth token' => [null, 'token-123', 'token-123'];
        yield 'rate-limited as unknown' => [null, null, 'unknown'];
    }

    #[DataProvider('rateLimitKeyProvider')]
    public function testRateLimitThrottlesRequest(?string $remoteAddr, ?string $tokenId, string $expectedKey): void
    {
        $request = new Request();
        if ($remoteAddr !== null) {
            $request->server->set('REMOTE_ADDR', $remoteAddr);
        }
        if ($tokenId !== null) {
            $request->attributes->set('oauth_access_token_id', $tokenId);
        }

        $rateLimitException = new RateLimitExceededException(time() + 60);

        $this->rateLimiter->expects($this->once())
            ->method('ensureAccepted')
            ->with(RateLimiter::MCP, $expectedKey)
            ->willThrowException($rateLimitException);

        $this->expectExceptionObject(McpException::throttled($rateLimitException->getWaitTime(), $rateLimitException));

        $this->controller->handle($request);
    }

    public function testToolCallBlockedWhenNotInAllowlist(): void
    {
        // The admin flag on an integration does NOT bypass the allowlist (layer 2) —
        // it only bypasses ACL (layer 3). This test covers both the generic case and
        // the admin-integration case: the controller never reads the admin flag.
        $body = json_encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'tools/call',
            'params' => ['name' => 'shopware-entity-read', 'arguments' => []],
        ], \JSON_THROW_ON_ERROR);

        $psrRequest = new ServerRequest('POST', '/api/_mcp', ['Content-Type' => 'application/json'], $body);
        $allowlistProvider = static::createStub(McpAllowlistProvider::class);
        $allowlistProvider->method('forCurrentRequest')->willReturn([
            'tools' => ['shopware-entity-search'],
            'resources' => null,
            'prompts' => null,
        ]);

        $controller = $this->buildController($psrRequest, null, $allowlistProvider);
        $sfRequest = Request::create('/api/_mcp', 'POST', content: $body);
        $response = $controller->handle($sfRequest);

        static::assertSame(200, $response->getStatusCode());
        $data = json_decode((string) $response->getContent(), true);
        static::assertSame(-32001, $data['error']['code']);
        static::assertStringContainsString('shopware-entity-read', $data['error']['message']);
        static::assertStringContainsString('allowlist', $data['error']['message']);
    }

    public function testNoToolsAllowedWhenToolsAllowlistIsEmptyArray(): void
    {
        $body = json_encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'tools/call',
            'params' => ['name' => 'shopware-entity-schema', 'arguments' => []],
        ], \JSON_THROW_ON_ERROR);

        $psrRequest = new ServerRequest('POST', '/api/_mcp', ['Content-Type' => 'application/json'], $body);
        $allowlistProvider = static::createStub(McpAllowlistProvider::class);
        $allowlistProvider->method('forCurrentRequest')->willReturn([
            'tools' => [],
            'resources' => null,
            'prompts' => null,
        ]);

        $controller = $this->buildController($psrRequest, null, $allowlistProvider);
        $sfRequest = Request::create('/api/_mcp', 'POST', content: $body);
        $response = $controller->handle($sfRequest);

        static::assertSame(200, $response->getStatusCode());
        $data = json_decode((string) $response->getContent(), true);
        static::assertSame(-32001, $data['error']['code']);
    }

    public function testResourceReadBlockedWhenNotInAllowlist(): void
    {
        $body = json_encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'resources/read',
            'params' => ['uri' => 'shopware://state-machines'],
        ], \JSON_THROW_ON_ERROR);

        $psrRequest = new ServerRequest('POST', '/api/_mcp', ['Content-Type' => 'application/json'], $body);
        $allowlistProvider = static::createStub(McpAllowlistProvider::class);
        $allowlistProvider->method('forCurrentRequest')->willReturn([
            'tools' => null,
            'resources' => ['shopware://entities'],
            'prompts' => null,
        ]);

        $controller = $this->buildController($psrRequest, null, $allowlistProvider);
        $sfRequest = Request::create('/api/_mcp', 'POST', content: $body);
        $response = $controller->handle($sfRequest);

        static::assertSame(200, $response->getStatusCode());
        $data = json_decode((string) $response->getContent(), true);
        static::assertSame(-32001, $data['error']['code']);
        static::assertStringContainsString('shopware://state-machines', $data['error']['message']);
    }

    public function testResourceReadAllowedWhenInAllowlist(): void
    {
        $body = json_encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'resources/read',
            'params' => ['uri' => 'shopware://entities'],
        ], \JSON_THROW_ON_ERROR);

        $psrRequest = new ServerRequest('POST', '/api/_mcp', ['Content-Type' => 'application/json'], $body);
        $httpFoundationFactory = static::createStub(HttpFoundationFactoryInterface::class);
        $httpFoundationFactory->method('createResponse')->willReturn(new Response('{}', 200));

        $allowlistProvider = static::createStub(McpAllowlistProvider::class);
        $allowlistProvider->method('forCurrentRequest')->willReturn([
            'tools' => null,
            'resources' => ['shopware://entities'],
            'prompts' => null,
        ]);

        $controller = $this->buildController($psrRequest, $httpFoundationFactory, $allowlistProvider);
        $sfRequest = Request::create('/api/_mcp', 'POST', content: $body);
        $response = $controller->handle($sfRequest);

        static::assertStringNotContainsString('allowlist', (string) $response->getContent());
    }

    public function testPromptGetBlockedWhenNotInAllowlist(): void
    {
        $body = json_encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'prompts/get',
            'params' => ['name' => 'shopware-developer'],
        ], \JSON_THROW_ON_ERROR);

        $psrRequest = new ServerRequest('POST', '/api/_mcp', ['Content-Type' => 'application/json'], $body);
        $allowlistProvider = static::createStub(McpAllowlistProvider::class);
        $allowlistProvider->method('forCurrentRequest')->willReturn([
            'tools' => null,
            'resources' => null,
            'prompts' => ['shopware-context'],
        ]);

        $controller = $this->buildController($psrRequest, null, $allowlistProvider);
        $sfRequest = Request::create('/api/_mcp', 'POST', content: $body);
        $response = $controller->handle($sfRequest);

        static::assertSame(200, $response->getStatusCode());
        $data = json_decode((string) $response->getContent(), true);
        static::assertSame(-32001, $data['error']['code']);
        static::assertStringContainsString('shopware-developer', $data['error']['message']);
    }

    public function testPromptGetAllowedWhenInAllowlist(): void
    {
        $body = json_encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'prompts/get',
            'params' => ['name' => 'shopware-context'],
        ], \JSON_THROW_ON_ERROR);

        $psrRequest = new ServerRequest('POST', '/api/_mcp', ['Content-Type' => 'application/json'], $body);
        $httpFoundationFactory = static::createStub(HttpFoundationFactoryInterface::class);
        $httpFoundationFactory->method('createResponse')->willReturn(new Response('{}', 200));

        $allowlistProvider = static::createStub(McpAllowlistProvider::class);
        $allowlistProvider->method('forCurrentRequest')->willReturn([
            'tools' => null,
            'resources' => null,
            'prompts' => ['shopware-context'],
        ]);

        $controller = $this->buildController($psrRequest, $httpFoundationFactory, $allowlistProvider);
        $sfRequest = Request::create('/api/_mcp', 'POST', content: $body);
        $response = $controller->handle($sfRequest);

        static::assertStringNotContainsString('allowlist', (string) $response->getContent());
    }

    public function testToolCallMissingNameReturnsGenericError(): void
    {
        $body = json_encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'tools/call',
            'params' => [],
        ], \JSON_THROW_ON_ERROR);

        $psrRequest = new ServerRequest('POST', '/api/_mcp', ['Content-Type' => 'application/json'], $body);
        $allowlistProvider = static::createStub(McpAllowlistProvider::class);
        $allowlistProvider->method('forCurrentRequest')->willReturn([
            'tools' => ['shopware-entity-search'],
            'resources' => null,
            'prompts' => null,
        ]);

        $controller = $this->buildController($psrRequest, null, $allowlistProvider);
        $sfRequest = Request::create('/api/_mcp', 'POST', content: $body);
        $response = $controller->handle($sfRequest);

        $data = json_decode((string) $response->getContent(), true);
        static::assertSame(-32001, $data['error']['code']);
        static::assertStringContainsString('no tool name', $data['error']['message']);
    }

    public function testResourceReadMissingUriReturnsGenericError(): void
    {
        $body = json_encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'resources/read',
            'params' => [],
        ], \JSON_THROW_ON_ERROR);

        $psrRequest = new ServerRequest('POST', '/api/_mcp', ['Content-Type' => 'application/json'], $body);
        $allowlistProvider = static::createStub(McpAllowlistProvider::class);
        $allowlistProvider->method('forCurrentRequest')->willReturn([
            'tools' => null,
            'resources' => ['shopware://entities'],
            'prompts' => null,
        ]);

        $controller = $this->buildController($psrRequest, null, $allowlistProvider);
        $sfRequest = Request::create('/api/_mcp', 'POST', content: $body);
        $response = $controller->handle($sfRequest);

        $data = json_decode((string) $response->getContent(), true);
        static::assertSame(-32001, $data['error']['code']);
        static::assertStringContainsString('no URI', $data['error']['message']);
    }

    public function testPromptGetMissingNameReturnsGenericError(): void
    {
        $body = json_encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'prompts/get',
            'params' => [],
        ], \JSON_THROW_ON_ERROR);

        $psrRequest = new ServerRequest('POST', '/api/_mcp', ['Content-Type' => 'application/json'], $body);
        $allowlistProvider = static::createStub(McpAllowlistProvider::class);
        $allowlistProvider->method('forCurrentRequest')->willReturn([
            'tools' => null,
            'resources' => null,
            'prompts' => ['shopware-context'],
        ]);

        $controller = $this->buildController($psrRequest, null, $allowlistProvider);
        $sfRequest = Request::create('/api/_mcp', 'POST', content: $body);
        $response = $controller->handle($sfRequest);

        $data = json_decode((string) $response->getContent(), true);
        static::assertSame(-32001, $data['error']['code']);
        static::assertStringContainsString('no prompt name', $data['error']['message']);
    }

    public function testResourcesListIsFilteredByAllowlist(): void
    {
        $server = Server::builder()
            ->addResource(static fn (): string => '', uri: 'shopware://resource-a', name: 'resource-a')
            ->addResource(static fn (): string => '', uri: 'shopware://resource-b', name: 'resource-b')
            ->build();

        $sessionId = $this->initializeMcpSession($server);

        $listBody = json_encode([
            'jsonrpc' => '2.0',
            'id' => 2,
            'method' => 'resources/list',
            'params' => [],
        ], \JSON_THROW_ON_ERROR);

        $allowlistProvider = static::createStub(McpAllowlistProvider::class);
        $allowlistProvider->method('forCurrentRequest')->willReturn([
            'tools' => null,
            'resources' => ['shopware://resource-a'],
            'prompts' => null,
        ]);

        $psrRequest = new ServerRequest(
            'POST',
            '/api/_mcp',
            ['Content-Type' => 'application/json', 'Mcp-Session-Id' => $sessionId],
            $listBody,
        );

        $controller = $this->buildController($psrRequest, new HttpFoundationFactory(), $allowlistProvider, server: $server);
        $sfRequest = Request::create('/api/_mcp', 'POST', content: $listBody, server: ['HTTP_MCP_SESSION_ID' => $sessionId]);
        $response = $controller->handle($sfRequest);

        $data = json_decode((string) $response->getContent(), true);
        $uris = array_column($data['result']['resources'] ?? [], 'uri');
        static::assertSame(['shopware://resource-a'], array_values($uris));
    }

    public function testPromptsListIsFilteredByAllowlist(): void
    {
        $server = Server::builder()
            ->addPrompt(static fn (): array => [], name: 'prompt-a', description: 'Prompt A')
            ->addPrompt(static fn (): array => [], name: 'prompt-b', description: 'Prompt B')
            ->build();

        $sessionId = $this->initializeMcpSession($server);

        $listBody = json_encode([
            'jsonrpc' => '2.0',
            'id' => 2,
            'method' => 'prompts/list',
            'params' => [],
        ], \JSON_THROW_ON_ERROR);

        $allowlistProvider = static::createStub(McpAllowlistProvider::class);
        $allowlistProvider->method('forCurrentRequest')->willReturn([
            'tools' => null,
            'resources' => null,
            'prompts' => ['prompt-a'],
        ]);

        $psrRequest = new ServerRequest(
            'POST',
            '/api/_mcp',
            ['Content-Type' => 'application/json', 'Mcp-Session-Id' => $sessionId],
            $listBody,
        );

        $controller = $this->buildController($psrRequest, new HttpFoundationFactory(), $allowlistProvider, server: $server);
        $sfRequest = Request::create('/api/_mcp', 'POST', content: $listBody, server: ['HTTP_MCP_SESSION_ID' => $sessionId]);
        $response = $controller->handle($sfRequest);

        $data = json_decode((string) $response->getContent(), true);
        $names = array_column($data['result']['prompts'] ?? [], 'name');
        static::assertSame(['prompt-a'], array_values($names));
    }

    public function testHandleLogsRequestWhenLoggerIsProvided(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('debug')
            ->with('MCP request', static::callback(static fn (array $ctx): bool => ($ctx['method'] ?? null) === 'GET' && \array_key_exists('clientIp', $ctx)));

        $psrRequest = new ServerRequest('GET', '/api/_mcp');
        $httpMessageFactory = static::createStub(HttpMessageFactoryInterface::class);
        $httpMessageFactory->method('createRequest')->willReturn($psrRequest);

        $httpFoundationFactory = static::createStub(HttpFoundationFactoryInterface::class);
        $httpFoundationFactory->method('createResponse')->willReturn(new Response('', 405));

        $psr17 = new Psr17Factory();
        $controller = new McpServerController(
            Server::builder()->build(),
            $httpMessageFactory,
            $httpFoundationFactory,
            $psr17,
            $psr17,
            static::createStub(RateLimiter::class),
            null,
            $logger,
            new McpAllowlistFilter(),
        );

        $controller->handle(new Request());
    }

    public function testInitializeEnrichmentSetsUserMetaWhenUserIdPresent(): void
    {
        $body = json_encode([
            'jsonrpc' => '2.0',
            'method' => 'initialize',
            'params' => [
                'protocolVersion' => '2025-03-26',
                'capabilities' => new \stdClass(),
                'clientInfo' => ['name' => 'test', 'version' => '1.0'],
            ],
            'id' => 1,
        ], \JSON_THROW_ON_ERROR);

        $psrRequest = new ServerRequest('POST', '/api/_mcp', ['Content-Type' => 'application/json'], $body);
        $controller = $this->buildController($psrRequest, new HttpFoundationFactory());
        $sfRequest = Request::create('/api/_mcp', 'POST', content: $body);
        $sfRequest->attributes->set(
            PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT,
            Context::createDefaultContext(new AdminApiSource('user-id-123')),
        );

        $response = $controller->handle($sfRequest);

        $data = json_decode((string) $response->getContent(), false, 512, \JSON_THROW_ON_ERROR);
        static::assertInstanceOf(\stdClass::class, $data);
        static::assertInstanceOf(\stdClass::class, $data->result);
        static::assertInstanceOf(\stdClass::class, $data->result->_meta);
        static::assertInstanceOf(\stdClass::class, $data->result->_meta->shopware);
        $userMeta = $data->result->_meta->shopware->user;
        static::assertInstanceOf(\stdClass::class, $userMeta);
        static::assertSame('user-id-123', $userMeta->id ?? null);
    }

    public function testInitializeEnrichmentSkippedWhenContextMissing(): void
    {
        $body = json_encode([
            'jsonrpc' => '2.0',
            'method' => 'initialize',
            'params' => [
                'protocolVersion' => '2025-03-26',
                'capabilities' => new \stdClass(),
                'clientInfo' => ['name' => 'test', 'version' => '1.0'],
            ],
            'id' => 1,
        ], \JSON_THROW_ON_ERROR);

        $psrRequest = new ServerRequest('POST', '/api/_mcp', ['Content-Type' => 'application/json'], $body);
        $controller = $this->buildController($psrRequest, new HttpFoundationFactory());
        $sfRequest = Request::create('/api/_mcp', 'POST', content: $body);

        $response = $controller->handle($sfRequest);

        $data = json_decode((string) $response->getContent(), false, 512, \JSON_THROW_ON_ERROR);
        static::assertInstanceOf(\stdClass::class, $data);
        $result = $data->result ?? new \stdClass();
        static::assertInstanceOf(\stdClass::class, $result);
        static::assertObjectNotHasProperty('_meta', $result);
    }

    public function testInitializeEnrichmentSkippedWhenSourceIsNotAdminApiSource(): void
    {
        $body = json_encode([
            'jsonrpc' => '2.0',
            'method' => 'initialize',
            'params' => [
                'protocolVersion' => '2025-03-26',
                'capabilities' => new \stdClass(),
                'clientInfo' => ['name' => 'test', 'version' => '1.0'],
            ],
            'id' => 1,
        ], \JSON_THROW_ON_ERROR);

        $psrRequest = new ServerRequest('POST', '/api/_mcp', ['Content-Type' => 'application/json'], $body);
        $controller = $this->buildController($psrRequest, new HttpFoundationFactory());
        $sfRequest = Request::create('/api/_mcp', 'POST', content: $body);
        $sfRequest->attributes->set(
            PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT,
            Context::createDefaultContext(),
        );

        $response = $controller->handle($sfRequest);

        $data = json_decode((string) $response->getContent(), false, 512, \JSON_THROW_ON_ERROR);
        static::assertInstanceOf(\stdClass::class, $data);
        $result = $data->result ?? new \stdClass();
        static::assertInstanceOf(\stdClass::class, $result);
        static::assertObjectNotHasProperty('_meta', $result);
    }

    public function testInitializeEnrichmentSkippedWhenSourceHasNoUserOrIntegration(): void
    {
        $body = json_encode([
            'jsonrpc' => '2.0',
            'method' => 'initialize',
            'params' => [
                'protocolVersion' => '2025-03-26',
                'capabilities' => new \stdClass(),
                'clientInfo' => ['name' => 'test', 'version' => '1.0'],
            ],
            'id' => 1,
        ], \JSON_THROW_ON_ERROR);

        $psrRequest = new ServerRequest('POST', '/api/_mcp', ['Content-Type' => 'application/json'], $body);
        $controller = $this->buildController($psrRequest, new HttpFoundationFactory());
        $sfRequest = Request::create('/api/_mcp', 'POST', content: $body);
        $sfRequest->attributes->set(
            PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT,
            Context::createDefaultContext(new AdminApiSource(null, null)),
        );

        $response = $controller->handle($sfRequest);

        $data = json_decode((string) $response->getContent(), false, 512, \JSON_THROW_ON_ERROR);
        static::assertInstanceOf(\stdClass::class, $data);
        $result = $data->result ?? new \stdClass();
        static::assertInstanceOf(\stdClass::class, $result);
        static::assertObjectNotHasProperty('_meta', $result);
    }

    public function testHandleReturnsNotFoundWhenFeatureFlagIsOff(): void
    {
        $_SERVER['MCP_SERVER'] = false;
        try {
            $controller = $this->buildController(new ServerRequest('POST', '/api/_mcp'));
            $response = $controller->handle(new Request());
            static::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        } finally {
            $_SERVER['MCP_SERVER'] = '1';
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function nullableConstructorArgProvider(): iterable
    {
        yield 'server is null' => ['server'];
        yield 'httpMessageFactory is null' => ['httpMessageFactory'];
        yield 'httpFoundationFactory is null' => ['httpFoundationFactory'];
        yield 'responseFactory is null' => ['responseFactory'];
        yield 'streamFactory is null' => ['streamFactory'];
    }

    #[DataProvider('nullableConstructorArgProvider')]
    public function testHandleReturnsNotFoundWhenAnyMcpBundleServiceIsNull(string $nullArg): void
    {
        $psr17 = new Psr17Factory();

        $controller = new McpServerController(
            $nullArg === 'server' ? null : Server::builder()->build(),
            $nullArg === 'httpMessageFactory' ? null : static::createStub(HttpMessageFactoryInterface::class),
            $nullArg === 'httpFoundationFactory' ? null : static::createStub(HttpFoundationFactoryInterface::class),
            $nullArg === 'responseFactory' ? null : $psr17,
            $nullArg === 'streamFactory' ? null : $psr17,
            static::createStub(RateLimiter::class),
        );

        $response = $controller->handle(new Request());

        static::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    /**
     * Performs the MCP initialize handshake and returns the session ID.
     * Must use the same server instance for subsequent requests so they share the in-memory session.
     */
    private function initializeMcpSession(Server $server): string
    {
        $psr17 = new Psr17Factory();
        $body = json_encode([
            'jsonrpc' => '2.0',
            'method' => 'initialize',
            'params' => [
                'protocolVersion' => '2025-03-26',
                'capabilities' => new \stdClass(),
                'clientInfo' => ['name' => 'test', 'version' => '1.0'],
            ],
            'id' => 1,
        ], \JSON_THROW_ON_ERROR);

        $httpMessageFactory = static::createStub(HttpMessageFactoryInterface::class);
        $httpMessageFactory->method('createRequest')->willReturn(
            new ServerRequest('POST', '/api/_mcp', ['Content-Type' => 'application/json'], $body),
        );

        $controller = new McpServerController(
            $server,
            $httpMessageFactory,
            new HttpFoundationFactory(),
            $psr17,
            $psr17,
            static::createStub(RateLimiter::class),
            allowlistFilter: new McpAllowlistFilter(),
        );

        $response = $controller->handle(new Request());

        return (string) $response->headers->get('Mcp-Session-Id');
    }

    private function buildController(
        ServerRequest $psrRequest,
        ?HttpFoundationFactoryInterface $httpFoundationFactory = null,
        ?McpAllowlistProvider $allowlistProvider = null,
        ?RateLimiter $rateLimiter = null,
        ?Server $server = null,
    ): McpServerController {
        $psr17 = new Psr17Factory();
        $httpMessageFactory = static::createStub(HttpMessageFactoryInterface::class);
        $httpMessageFactory->method('createRequest')->willReturn($psrRequest);

        return new McpServerController(
            $server ?? Server::builder()->build(),
            $httpMessageFactory,
            $httpFoundationFactory ?? static::createStub(HttpFoundationFactoryInterface::class),
            $psr17,
            $psr17,
            $rateLimiter ?? static::createStub(RateLimiter::class),
            $allowlistProvider,
            allowlistFilter: new McpAllowlistFilter(),
        );
    }
}
