<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp\Controller;

use Mcp\Server;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Mcp\Controller\StoreApiMcpServerController;
use Shopware\Core\Framework\Mcp\McpException;
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

/**
 * @internal
 */
#[CoversClass(StoreApiMcpServerController::class)]
class StoreApiMcpServerControllerTest extends TestCase
{
    private RateLimiter&MockObject $rateLimiter;

    private Psr17Factory $psr17;

    protected function setUp(): void
    {
        $_SERVER['MCP_SERVER'] = '1';
        $this->rateLimiter = $this->createMock(RateLimiter::class);
        $this->psr17 = new Psr17Factory();
    }

    protected function tearDown(): void
    {
        unset($_SERVER['MCP_SERVER']);
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

        $psrRequest = new ServerRequest('POST', '/store-api/_mcp', ['Content-Type' => 'application/json'], $body);
        $controller = $this->buildController($psrRequest, new HttpFoundationFactory());

        $sfRequest = Request::create('/store-api/_mcp', 'POST', content: $body);
        $sfRequest->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT, $this->createSalesChannelContext());

        $response = $controller->handle($sfRequest);

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
    }

    public function testHandleReturnsNotFoundWhenFeatureFlagIsInactive(): void
    {
        $_SERVER['MCP_SERVER'] = false;

        $controller = $this->buildController(new ServerRequest('POST', '/store-api/_mcp'));

        static::assertSame(Response::HTTP_NOT_FOUND, $controller->handle(new Request())->getStatusCode());
    }

    public function testRateLimitUsesSalesChannelContext(): void
    {
        $salesChannelContext = $this->createSalesChannelContext();

        $this->rateLimiter
            ->expects($this->once())
            ->method('ensureAccepted')
            ->with(RateLimiter::MCP_STORE_API, 'sales-channel-id-context-token');

        $controller = $this->buildController(
            new ServerRequest('GET', '/store-api/_mcp'),
            static::createStub(HttpFoundationFactoryInterface::class),
        );

        $sfRequest = Request::create('/store-api/_mcp', 'GET');
        $sfRequest->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT, $salesChannelContext);

        $controller->handle($sfRequest);
    }

    public function testRateLimitExceptionIsConvertedToMcpException(): void
    {
        Clock::set(new MockClock('2026-01-01 00:00:00'));
        $rateLimitException = new RateLimitExceededException((new \DateTimeImmutable('2026-01-01 00:01:00'))->getTimestamp());

        $this->rateLimiter
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

    private function buildController(
        ServerRequest $psrRequest,
        ?HttpFoundationFactoryInterface $httpFoundationFactory = null,
        ?Server $server = null,
    ): StoreApiMcpServerController {
        $httpMessageFactory = static::createStub(HttpMessageFactoryInterface::class);
        $httpMessageFactory->method('createRequest')->willReturn($psrRequest);

        return new StoreApiMcpServerController(
            $server ?? Server::builder()->build(),
            $httpMessageFactory,
            $httpFoundationFactory ?? static::createStub(HttpFoundationFactoryInterface::class),
            $this->psr17,
            $this->psr17,
            new McpRateLimiter($this->rateLimiter),
            new McpSessionIdValidator(),
        );
    }

    private function createSalesChannelContext(): SalesChannelContext&MockObject
    {
        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannelContext->method('getSalesChannelId')->willReturn('sales-channel-id');
        $salesChannelContext->method('getToken')->willReturn('context-token');

        return $salesChannelContext;
    }
}
