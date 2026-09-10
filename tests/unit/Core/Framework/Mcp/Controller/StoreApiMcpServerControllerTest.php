<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp\Controller;

use Mcp\Server;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Controller\StoreApiMcpServerController;
use Shopware\Core\Framework\Mcp\Http\McpHttpTransportFactory;
use Shopware\Core\Framework\Mcp\McpAllowedHostsProvider;
use Shopware\Core\Framework\Mcp\McpException;
use Shopware\Core\Framework\Mcp\Notification\McpListChangedNotificationSet;
use Shopware\Core\Framework\Mcp\Notification\McpListChangedNotifier;
use Shopware\Core\Framework\Mcp\Notification\McpSessionRegistry;
use Shopware\Core\Framework\Mcp\RateLimit\McpRateLimiter;
use Shopware\Core\Framework\Mcp\Session\McpSessionIdValidator;
use Shopware\Core\Framework\RateLimiter\Exception\RateLimitExceededException;
use Shopware\Core\Framework\RateLimiter\RateLimiter;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory;
use Symfony\Bridge\PsrHttpMessage\HttpFoundationFactoryInterface;
use Symfony\Bridge\PsrHttpMessage\HttpMessageFactoryInterface;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Uid\Uuid;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(StoreApiMcpServerController::class)]
class StoreApiMcpServerControllerTest extends TestCase
{
    private RateLimiter&MockObject $rateLimiter;

    private Psr17Factory $psr17;

    protected function setUp(): void
    {
        $this->rateLimiter = $this->createMock(RateLimiter::class);
        $this->psr17 = new Psr17Factory();
    }

    protected function tearDown(): void
    {
        Clock::set(new NativeClock());
    }

    public function testHandleReturnsResponseForValidStoreApiMcpRequest(): void
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

        $this->rateLimiter->expects($this->atLeastOnce())->method('ensureAccepted');

        $psrRequest = new ServerRequest('POST', '/store-api/_mcp', ['Content-Type' => 'application/json'], $body);
        $controller = $this->buildController($psrRequest, new HttpFoundationFactory());

        $sfRequest = Request::create('/store-api/_mcp', 'POST', content: $body);
        $sfRequest->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT, $this->createSalesChannelContext());

        $response = $controller->handle($sfRequest);

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
    }

    public function testRateLimitUsesSalesChannelContextAndClientIp(): void
    {
        $salesChannelContext = $this->createSalesChannelContext();

        // The context token is rotatable, so the endpoint is throttled on both the per-context key
        // and a stable per-IP key (same mcp_store_api bucket).
        $calls = [];
        $this->rateLimiter
            ->expects($this->exactly(2))
            ->method('ensureAccepted')
            ->willReturnCallback(static function (string $route, string $key) use (&$calls): void {
                $calls[] = [$route, $key];
            });

        $controller = $this->buildController(
            new ServerRequest('GET', '/store-api/_mcp'),
            static::createStub(HttpFoundationFactoryInterface::class),
        );

        $sfRequest = Request::create('/store-api/_mcp', 'GET');
        $sfRequest->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT, $salesChannelContext);

        $controller->handle($sfRequest);

        static::assertSame([
            [RateLimiter::MCP_STORE_API, 'sales-channel-id-context-token'],
            [RateLimiter::MCP_STORE_API, '127.0.0.1'],
        ], $calls);
    }

    public function testRateLimitExceptionIsConvertedToMcpException(): void
    {
        Clock::set(new MockClock('2026-01-01 00:00:00'));
        $rateLimitException = new RateLimitExceededException((new \DateTimeImmutable('2026-01-01 00:01:00'))->getTimestamp());

        $this->rateLimiter
            ->expects($this->once())
            ->method('ensureAccepted')
            ->willThrowException($rateLimitException);

        $controller = $this->buildController(new ServerRequest('GET', '/store-api/_mcp'));

        $this->expectExceptionObject(McpException::throttled(60, $rateLimitException));

        $controller->handle(new Request());
    }

    public function testMalformedSessionIdHeaderIsRejected(): void
    {
        $this->rateLimiter
            ->expects($this->never())
            ->method('ensureAccepted');

        $controller = $this->buildController(new ServerRequest('POST', '/store-api/_mcp'));

        $request = Request::create('/store-api/_mcp', 'POST');
        $request->headers->set(PlatformRequest::HEADER_MCP_SESSION_ID, 'not-a-uuid');

        $this->expectExceptionObject(McpException::invalidSessionId());

        $controller->handle($request);
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

        $this->rateLimiter->expects($this->atLeastOnce())->method('ensureAccepted');

        $sessionRegistry = $this->createMock(McpSessionRegistry::class);
        $sessionRegistry->expects($this->once())
            ->method('register')
            ->with(static::callback(static fn (string $sessionId): bool => $sessionId !== ''));

        $psrRequest = new ServerRequest('POST', '/store-api/_mcp', ['Content-Type' => 'application/json'], $body);
        $controller = $this->buildController(
            $psrRequest,
            new HttpFoundationFactory(),
            sessionRegistry: $sessionRegistry,
        );

        $sfRequest = Request::create('/store-api/_mcp', 'POST', content: $body);
        $sfRequest->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT, $this->createSalesChannelContext());

        $response = $controller->handle($sfRequest);

        static::assertNotSame('', (string) $response->headers->get(PlatformRequest::HEADER_MCP_SESSION_ID));
    }

    public function testDoesNotRegisterSessionWhenResponseHasNoSessionHeader(): void
    {
        $this->rateLimiter->expects($this->atLeastOnce())->method('ensureAccepted');

        $sessionRegistry = $this->createMock(McpSessionRegistry::class);
        $sessionRegistry->expects($this->never())->method('register');

        $httpFoundationFactory = static::createStub(HttpFoundationFactoryInterface::class);
        $httpFoundationFactory->method('createResponse')->willReturn(new Response('', 405));

        $controller = $this->buildController(
            new ServerRequest('GET', '/store-api/_mcp'),
            $httpFoundationFactory,
            sessionRegistry: $sessionRegistry,
        );

        $sfRequest = Request::create('/store-api/_mcp', 'GET');
        $sfRequest->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT, $this->createSalesChannelContext());

        $response = $controller->handle($sfRequest);

        static::assertSame(405, $response->getStatusCode());
    }

    public function testHandleReturnsNotFoundWhenServerIsNull(): void
    {
        $this->rateLimiter->expects($this->never())->method('ensureAccepted');

        $controller = new StoreApiMcpServerController(
            null,
            new McpHttpTransportFactory(
                static::createStub(HttpMessageFactoryInterface::class),
                $this->psr17,
                $this->psr17,
                static::createStub(HttpFoundationFactoryInterface::class),
                static::createStub(McpAllowedHostsProvider::class),
            ),
            new McpRateLimiter($this->rateLimiter),
            new McpSessionIdValidator(),
        );

        static::assertSame(Response::HTTP_NOT_FOUND, $controller->handle(new Request())->getStatusCode());
    }

    public function testHandleReturnsNotFoundWhenTransportFactoryIsUnavailable(): void
    {
        $this->rateLimiter->expects($this->never())->method('ensureAccepted');

        // A transport factory built without the PhpMcp bundle factories reports itself unavailable.
        $unavailable = new McpHttpTransportFactory(
            null,
            null,
            null,
            null,
            static::createStub(McpAllowedHostsProvider::class),
        );

        $controller = new StoreApiMcpServerController(
            Server::builder()->build(),
            $unavailable,
            new McpRateLimiter($this->rateLimiter),
            new McpSessionIdValidator(),
        );

        static::assertSame(Response::HTTP_NOT_FOUND, $controller->handle(new Request())->getStatusCode());
    }

    public function testFlushesPendingToolsListChangedForActiveSession(): void
    {
        $this->rateLimiter->expects($this->atLeastOnce())->method('ensureAccepted');

        $sessionId = Uuid::v4()->toRfc4122();

        $notifier = $this->createMock(McpListChangedNotifier::class);
        $notifier->expects($this->once())
            ->method('notifySession')
            ->with(
                $sessionId,
                static::callback(static fn (McpListChangedNotificationSet $set): bool => $set->tools && !$set->resources && !$set->prompts),
            );

        $controller = $this->buildController(
            new ServerRequest('POST', '/store-api/_mcp'),
            new HttpFoundationFactory(),
            listChangedNotifier: $notifier,
        );

        $request = Request::create('/store-api/_mcp', 'POST');
        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT, $this->createSalesChannelContext());
        $request->attributes->set(McpListChangedNotifier::PENDING_TOOLS_LIST_CHANGED_ATTRIBUTE, true);
        $request->headers->set(PlatformRequest::HEADER_MCP_SESSION_ID, $sessionId);

        $controller->handle($request);
    }

    public function testDoesNotFlushWhenNoPendingNotification(): void
    {
        $this->rateLimiter->expects($this->atLeastOnce())->method('ensureAccepted');

        $notifier = $this->createMock(McpListChangedNotifier::class);
        $notifier->expects($this->never())->method('notifySession');

        $controller = $this->buildController(
            new ServerRequest('POST', '/store-api/_mcp'),
            new HttpFoundationFactory(),
            listChangedNotifier: $notifier,
        );

        $request = Request::create('/store-api/_mcp', 'POST');
        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT, $this->createSalesChannelContext());
        $request->headers->set(PlatformRequest::HEADER_MCP_SESSION_ID, Uuid::v4()->toRfc4122());

        $controller->handle($request);
    }

    public function testDoesNotFlushWhenSessionHeaderMissing(): void
    {
        $this->rateLimiter->expects($this->atLeastOnce())->method('ensureAccepted');

        $notifier = $this->createMock(McpListChangedNotifier::class);
        $notifier->expects($this->never())->method('notifySession');

        $controller = $this->buildController(
            new ServerRequest('POST', '/store-api/_mcp'),
            new HttpFoundationFactory(),
            listChangedNotifier: $notifier,
        );

        $request = Request::create('/store-api/_mcp', 'POST');
        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT, $this->createSalesChannelContext());
        $request->attributes->set(McpListChangedNotifier::PENDING_TOOLS_LIST_CHANGED_ATTRIBUTE, true);

        $controller->handle($request);
    }

    private function buildController(
        ServerRequest $psrRequest,
        ?HttpFoundationFactoryInterface $httpFoundationFactory = null,
        ?Server $server = null,
        ?McpSessionRegistry $sessionRegistry = null,
        ?McpListChangedNotifier $listChangedNotifier = null,
    ): StoreApiMcpServerController {
        $httpMessageFactory = static::createStub(HttpMessageFactoryInterface::class);
        $httpMessageFactory->method('createRequest')->willReturn($psrRequest);

        $transportFactory = new McpHttpTransportFactory(
            $httpMessageFactory,
            $this->psr17,
            $this->psr17,
            $httpFoundationFactory ?? static::createStub(HttpFoundationFactoryInterface::class),
            static::createStub(McpAllowedHostsProvider::class),
        );

        return new StoreApiMcpServerController(
            $server ?? Server::builder()->build(),
            $transportFactory,
            new McpRateLimiter($this->rateLimiter),
            new McpSessionIdValidator(),
            sessionRegistry: $sessionRegistry,
            listChangedNotifier: $listChangedNotifier,
        );
    }

    private function createSalesChannelContext(): SalesChannelContext&Stub
    {
        $salesChannelContext = static::createStub(SalesChannelContext::class);
        $salesChannelContext->method('getSalesChannelId')->willReturn('sales-channel-id');
        $salesChannelContext->method('getToken')->willReturn('context-token');

        return $salesChannelContext;
    }
}
