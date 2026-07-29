<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp\Controller;

use Mcp\Server;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\AllowList\McpAllowlist;
use Shopware\Core\Framework\Mcp\AllowList\McpAllowlistFilter;
use Shopware\Core\Framework\Mcp\AllowList\McpAllowlistProvider;
use Shopware\Core\Framework\Mcp\Controller\McpServerController;
use Shopware\Core\Framework\Mcp\Http\McpHttpTransportFactory;
use Shopware\Core\Framework\Mcp\McpAllowedHostsProvider;
use Shopware\Core\Framework\Mcp\McpException;
use Shopware\Core\Framework\Mcp\McpToolsetRegistry;
use Shopware\Core\Framework\Mcp\Notification\McpListChangedNotificationSet;
use Shopware\Core\Framework\Mcp\Notification\McpListChangedNotifier;
use Shopware\Core\Framework\Mcp\Notification\McpSessionRegistry;
use Shopware\Core\Framework\Mcp\RateLimit\McpRateLimiter;
use Shopware\Core\Framework\Mcp\Session\McpSessionIdValidator;
use Shopware\Core\Framework\RateLimiter\Exception\RateLimitExceededException;
use Shopware\Core\Framework\RateLimiter\RateLimiter;
use Shopware\Core\PlatformRequest;
use Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory;
use Symfony\Bridge\PsrHttpMessage\HttpFoundationFactoryInterface;
use Symfony\Bridge\PsrHttpMessage\HttpMessageFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Uid\Uuid;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(McpServerController::class)]
#[CoversClass(McpAllowlistFilter::class)]
class McpServerControllerTest extends TestCase
{
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

    public function testMalformedSessionIdHeaderIsRejected(): void
    {
        $rateLimiter = $this->createMock(RateLimiter::class);
        $rateLimiter->expects($this->never())->method('ensureAccepted');
        $controller = $this->controllerWithRateLimiter($rateLimiter);

        $request = Request::create('/api/_mcp', 'POST');
        $request->headers->set(PlatformRequest::HEADER_MCP_SESSION_ID, 'not-a-uuid');

        $this->expectExceptionObject(McpException::invalidSessionId());

        $controller->handle($request);
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

    public function testInitializeRegistersMcpSession(): void
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

        $sessionRegistry = $this->createMock(McpSessionRegistry::class);
        $sessionRegistry->expects($this->once())
            ->method('register')
            ->with(static::callback(static fn (string $sessionId): bool => $sessionId !== ''));

        $psrRequest = new ServerRequest('POST', '/api/_mcp', ['Content-Type' => 'application/json'], $body);
        $controller = $this->buildController(
            $psrRequest,
            new HttpFoundationFactory(),
            sessionRegistry: $sessionRegistry,
        );

        $response = $controller->handle(Request::create('/api/_mcp', 'POST', content: $body));

        static::assertNotSame('', (string) $response->headers->get(PlatformRequest::HEADER_MCP_SESSION_ID));
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

    public function testDoesNotRegisterSessionWhenResponseHasNoSessionHeader(): void
    {
        $sessionRegistry = $this->createMock(McpSessionRegistry::class);
        $sessionRegistry->expects($this->never())->method('register');

        $psrRequest = new ServerRequest('GET', '/api/_mcp');
        $httpFoundationFactory = static::createStub(HttpFoundationFactoryInterface::class);
        $httpFoundationFactory->method('createResponse')->willReturn(new Response('', 405));

        $controller = $this->buildController(
            $psrRequest,
            $httpFoundationFactory,
            sessionRegistry: $sessionRegistry,
        );

        $response = $controller->handle(new Request());

        static::assertSame(405, $response->getStatusCode());
    }

    public function testPostWithMalformedJsonBodyDoesNotSetJsonRpcAttributeAndPassesThrough(): void
    {
        $psrRequest = new ServerRequest('POST', '/api/_mcp', ['Content-Type' => 'application/json'], 'not-json');
        $httpFoundationFactory = static::createStub(HttpFoundationFactoryInterface::class);
        $httpFoundationFactory->method('createResponse')->willReturn(new Response('', 400));

        $allowlistProvider = static::createStub(McpAllowlistProvider::class);
        $allowlistProvider->method('forCurrentRequest')->willReturn(new McpAllowlist(tools: ['shopware-entity-search'], resources: null, prompts: null));

        $controller = $this->buildController($psrRequest, $httpFoundationFactory, $allowlistProvider);
        $sfRequest = Request::create('/api/_mcp', 'POST', content: 'not-json');
        $response = $controller->handle($sfRequest);

        static::assertFalse($sfRequest->attributes->has(McpServerController::ATTRIBUTE_JSONRPC_BODY));
        static::assertSame(400, $response->getStatusCode());
    }

    /**
     * @return iterable<string, array{string, McpAllowlist}>
     */
    public static function allowedToolCallProvider(): iterable
    {
        yield 'tool explicitly in allowlist' => [
            'shopware-entity-search',
            new McpAllowlist(tools: ['shopware-entity-search'], resources: null, prompts: null),
        ];
        yield 'null tools allows all tools' => [
            'any-tool',
            new McpAllowlist(tools: null, resources: null, prompts: null),
        ];
    }

    #[DataProvider('allowedToolCallProvider')]
    public function testToolCallNotBlockedWhenAllowed(string $toolName, McpAllowlist $allowlist): void
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

    public function testToolSearchCallIsAllowedEvenWhenToolAllowlistIsEmpty(): void
    {
        $body = json_encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'tools/call',
            'params' => ['name' => 'shopware-tool-search', 'arguments' => ['query' => 'entity']],
        ], \JSON_THROW_ON_ERROR);

        $psrRequest = new ServerRequest('POST', '/api/_mcp', ['Content-Type' => 'application/json'], $body);
        $httpFoundationFactory = static::createStub(HttpFoundationFactoryInterface::class);
        $httpFoundationFactory->method('createResponse')->willReturn(new Response('{}', 200));

        $allowlistProvider = static::createStub(McpAllowlistProvider::class);
        $allowlistProvider->method('forCurrentRequest')->willReturn(new McpAllowlist(tools: [], resources: null, prompts: null));

        $controller = $this->buildController($psrRequest, $httpFoundationFactory, $allowlistProvider);
        $sfRequest = Request::create('/api/_mcp', 'POST', content: $body);
        $response = $controller->handle($sfRequest);

        static::assertStringNotContainsString('allowlist', (string) $response->getContent());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function discoveryMetaToolProvider(): iterable
    {
        yield 'toolsets-list' => [McpToolsetRegistry::LIST_TOOLSETS_TOOL];
        yield 'toolset-enable' => [McpToolsetRegistry::ENABLE_TOOLSET_TOOL];
    }

    #[DataProvider('discoveryMetaToolProvider')]
    public function testDiscoveryMetaToolCallIsAllowedEvenWhenToolAllowlistIsEmpty(string $toolName): void
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
        $allowlistProvider->method('forCurrentRequest')->willReturn(new McpAllowlist(tools: [], resources: null, prompts: null));

        $controller = $this->buildController($psrRequest, $httpFoundationFactory, $allowlistProvider);
        $sfRequest = Request::create('/api/_mcp', 'POST', content: $body);
        $response = $controller->handle($sfRequest);

        static::assertStringNotContainsString('allowlist', (string) $response->getContent());
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

        $rateLimitException = new RateLimitExceededException((new \DateTimeImmutable('+60 seconds'))->getTimestamp());

        $rateLimiter = $this->createMock(RateLimiter::class);
        $rateLimiter->expects($this->once())
            ->method('ensureAccepted')
            ->with(RateLimiter::MCP_ADMIN_API, $expectedKey)
            ->willThrowException($rateLimitException);
        $controller = $this->controllerWithRateLimiter($rateLimiter);

        $this->expectExceptionObject(McpException::throttled($rateLimitException->getWaitTime(), $rateLimitException));

        $controller->handle($request);
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
        $allowlistProvider->method('forCurrentRequest')->willReturn(new McpAllowlist(tools: ['shopware-entity-search'], resources: null, prompts: null));

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
        $allowlistProvider->method('forCurrentRequest')->willReturn(new McpAllowlist(tools: [], resources: null, prompts: null));

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
        $allowlistProvider->method('forCurrentRequest')->willReturn(new McpAllowlist(tools: null, resources: ['shopware://entities'], prompts: null));

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
        $allowlistProvider->method('forCurrentRequest')->willReturn(new McpAllowlist(tools: null, resources: ['shopware://entities'], prompts: null));

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
        $allowlistProvider->method('forCurrentRequest')->willReturn(new McpAllowlist(tools: null, resources: null, prompts: ['shopware-context']));

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
        $allowlistProvider->method('forCurrentRequest')->willReturn(new McpAllowlist(tools: null, resources: null, prompts: ['shopware-context']));

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
        $allowlistProvider->method('forCurrentRequest')->willReturn(new McpAllowlist(tools: ['shopware-entity-search'], resources: null, prompts: null));

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
        $allowlistProvider->method('forCurrentRequest')->willReturn(new McpAllowlist(tools: null, resources: ['shopware://entities'], prompts: null));

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
        $allowlistProvider->method('forCurrentRequest')->willReturn(new McpAllowlist(tools: null, resources: null, prompts: ['shopware-context']));

        $controller = $this->buildController($psrRequest, null, $allowlistProvider);
        $sfRequest = Request::create('/api/_mcp', 'POST', content: $body);
        $response = $controller->handle($sfRequest);

        $data = json_decode((string) $response->getContent(), true);
        static::assertSame(-32001, $data['error']['code']);
        static::assertStringContainsString('no prompt name', $data['error']['message']);
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
        $transportFactory = new McpHttpTransportFactory(
            $httpMessageFactory,
            $psr17,
            $psr17,
            $httpFoundationFactory,
            static::createStub(McpAllowedHostsProvider::class),
        );
        $controller = new McpServerController(
            Server::builder()->build(),
            $transportFactory,
            new McpRateLimiter(static::createStub(RateLimiter::class)),
            new McpSessionIdValidator(),
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

    public function testHandleReturnsNotFoundWhenServerIsNull(): void
    {
        $controller = new McpServerController(
            null,
            $this->transportFactory(),
            new McpRateLimiter(static::createStub(RateLimiter::class)),
            new McpSessionIdValidator(),
        );

        static::assertSame(Response::HTTP_NOT_FOUND, $controller->handle(new Request())->getStatusCode());
    }

    public function testHandleReturnsNotFoundWhenTransportFactoryIsUnavailable(): void
    {
        // A transport factory built without the PhpMcp bundle factories reports itself unavailable.
        $unavailable = new McpHttpTransportFactory(null, null, null, null, static::createStub(McpAllowedHostsProvider::class));

        $controller = new McpServerController(
            Server::builder()->build(),
            $unavailable,
            new McpRateLimiter(static::createStub(RateLimiter::class)),
            new McpSessionIdValidator(),
        );

        static::assertSame(Response::HTTP_NOT_FOUND, $controller->handle(new Request())->getStatusCode());
    }

    public function testFlushesPendingToolsListChangedForActiveSession(): void
    {
        $sessionId = Uuid::v4()->toRfc4122();

        $notifier = $this->createMock(McpListChangedNotifier::class);
        $notifier->expects($this->once())
            ->method('notifySession')
            ->with(
                $sessionId,
                static::callback(static fn (McpListChangedNotificationSet $set): bool => $set->tools && !$set->resources && !$set->prompts),
            );

        $controller = $this->buildController(
            new ServerRequest('POST', '/api/_mcp'),
            new HttpFoundationFactory(),
            listChangedNotifier: $notifier,
        );

        $request = Request::create('/api/_mcp', 'POST');
        $request->attributes->set(McpListChangedNotifier::PENDING_TOOLS_LIST_CHANGED_ATTRIBUTE, true);
        $request->headers->set(PlatformRequest::HEADER_MCP_SESSION_ID, $sessionId);

        $controller->handle($request);
    }

    public function testDoesNotFlushWhenNoPendingNotification(): void
    {
        $notifier = $this->createMock(McpListChangedNotifier::class);
        $notifier->expects($this->never())->method('notifySession');

        $controller = $this->buildController(
            new ServerRequest('POST', '/api/_mcp'),
            new HttpFoundationFactory(),
            listChangedNotifier: $notifier,
        );

        $request = Request::create('/api/_mcp', 'POST');
        $request->headers->set(PlatformRequest::HEADER_MCP_SESSION_ID, Uuid::v4()->toRfc4122());

        $controller->handle($request);
    }

    public function testDoesNotFlushWhenSessionHeaderMissing(): void
    {
        $notifier = $this->createMock(McpListChangedNotifier::class);
        $notifier->expects($this->never())->method('notifySession');

        $controller = $this->buildController(
            new ServerRequest('POST', '/api/_mcp'),
            new HttpFoundationFactory(),
            listChangedNotifier: $notifier,
        );

        $request = Request::create('/api/_mcp', 'POST');
        $request->attributes->set(McpListChangedNotifier::PENDING_TOOLS_LIST_CHANGED_ATTRIBUTE, true);

        $controller->handle($request);
    }

    private function controllerWithRateLimiter(RateLimiter $rateLimiter): McpServerController
    {
        return new McpServerController(
            Server::builder()->build(),
            $this->transportFactory(),
            new McpRateLimiter($rateLimiter),
            new McpSessionIdValidator(),
        );
    }

    private function transportFactory(): McpHttpTransportFactory
    {
        $psr17 = new Psr17Factory();

        return new McpHttpTransportFactory(
            static::createStub(HttpMessageFactoryInterface::class),
            $psr17,
            $psr17,
            static::createStub(HttpFoundationFactoryInterface::class),
            static::createStub(McpAllowedHostsProvider::class),
        );
    }

    private function buildController(
        ServerRequest $psrRequest,
        ?HttpFoundationFactoryInterface $httpFoundationFactory = null,
        ?McpAllowlistProvider $allowlistProvider = null,
        ?RateLimiter $rateLimiter = null,
        ?Server $server = null,
        ?McpSessionRegistry $sessionRegistry = null,
        ?McpListChangedNotifier $listChangedNotifier = null,
    ): McpServerController {
        $psr17 = new Psr17Factory();
        $httpMessageFactory = static::createStub(HttpMessageFactoryInterface::class);
        $httpMessageFactory->method('createRequest')->willReturn($psrRequest);

        $transportFactory = new McpHttpTransportFactory(
            $httpMessageFactory,
            $psr17,
            $psr17,
            $httpFoundationFactory ?? static::createStub(HttpFoundationFactoryInterface::class),
            static::createStub(McpAllowedHostsProvider::class),
        );

        return new McpServerController(
            $server ?? Server::builder()->build(),
            $transportFactory,
            new McpRateLimiter($rateLimiter ?? static::createStub(RateLimiter::class)),
            new McpSessionIdValidator(),
            $allowlistProvider,
            allowlistFilter: new McpAllowlistFilter(),
            sessionRegistry: $sessionRegistry,
            listChangedNotifier: $listChangedNotifier,
        );
    }
}
