<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp\RateLimit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\McpException;
use Shopware\Core\Framework\Mcp\RateLimit\McpRateLimiter;
use Shopware\Core\Framework\RateLimiter\Exception\RateLimitExceededException;
use Shopware\Core\Framework\RateLimiter\RateLimiter;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(McpRateLimiter::class)]
class McpRateLimiterTest extends TestCase
{
    private RateLimiter&MockObject $rateLimiter;

    private McpRateLimiter $mcpRateLimiter;

    protected function setUp(): void
    {
        $this->rateLimiter = $this->createMock(RateLimiter::class);
        $this->mcpRateLimiter = new McpRateLimiter($this->rateLimiter);
    }

    /**
     * @return iterable<string, array{?string, ?string, string}>
     */
    public static function adminApiKeyProvider(): iterable
    {
        yield 'keyed by OAuth token' => [null, 'token-123', 'token-123'];
        yield 'falls back to client IP' => ['192.168.1.1', null, '192.168.1.1'];
        yield 'falls back to unknown' => [null, null, 'unknown'];
    }

    #[DataProvider('adminApiKeyProvider')]
    public function testEnforceForAdminApiUsesExpectedKey(?string $remoteAddr, ?string $tokenId, string $expectedKey): void
    {
        $request = new Request();
        if ($remoteAddr !== null) {
            $request->server->set('REMOTE_ADDR', $remoteAddr);
        }
        if ($tokenId !== null) {
            $request->attributes->set(PlatformRequest::ATTRIBUTE_OAUTH_ACCESS_TOKEN_ID, $tokenId);
        }

        $this->rateLimiter->expects($this->once())
            ->method('ensureAccepted')
            ->with(RateLimiter::MCP_ADMIN_API, $expectedKey);

        $this->mcpRateLimiter->enforceForAdminApi($request);
    }

    public function testEnforceForStoreApiEnforcesBothContextAndPerIpBuckets(): void
    {
        $salesChannelContext = static::createStub(SalesChannelContext::class);
        $salesChannelContext->method('getSalesChannelId')->willReturn('sales-channel-id');
        $salesChannelContext->method('getToken')->willReturn('context-token');

        $request = new Request();
        $request->server->set('REMOTE_ADDR', '192.168.1.1');
        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT, $salesChannelContext);

        $calls = [];
        $this->rateLimiter->expects($this->exactly(2))
            ->method('ensureAccepted')
            ->willReturnCallback(static function (string $route, string $key) use (&$calls): void {
                $calls[] = [$route, $key];
            });

        $this->mcpRateLimiter->enforceForStoreApi($request);

        static::assertSame([
            [RateLimiter::MCP_STORE_API, 'sales-channel-id-context-token'],
            [RateLimiter::MCP_STORE_API, '192.168.1.1'],
        ], $calls);
    }

    /**
     * @return iterable<string, array{?string, string}>
     */
    public static function storeApiFallbackKeyProvider(): iterable
    {
        yield 'falls back to client IP' => ['192.168.1.1', '192.168.1.1'];
        yield 'falls back to unknown' => [null, 'unknown'];
    }

    #[DataProvider('storeApiFallbackKeyProvider')]
    public function testEnforceForStoreApiWithoutContextOnlyEnforcesPerIpBucket(?string $remoteAddr, string $expectedKey): void
    {
        $request = new Request();
        if ($remoteAddr !== null) {
            $request->server->set('REMOTE_ADDR', $remoteAddr);
        }

        $this->rateLimiter->expects($this->once())
            ->method('ensureAccepted')
            ->with(RateLimiter::MCP_STORE_API, $expectedKey);

        $this->mcpRateLimiter->enforceForStoreApi($request);
    }

    public function testEnforceForAdminApiTranslatesRateLimitException(): void
    {
        $rateLimitException = new RateLimitExceededException((new \DateTimeImmutable('+60 seconds'))->getTimestamp());

        $this->rateLimiter->expects($this->once())->method('ensureAccepted')->willThrowException($rateLimitException);

        $this->expectExceptionObject(McpException::throttled($rateLimitException->getWaitTime(), $rateLimitException));

        $this->mcpRateLimiter->enforceForAdminApi(new Request());
    }

    public function testEnforceForStoreApiTranslatesRateLimitException(): void
    {
        $rateLimitException = new RateLimitExceededException((new \DateTimeImmutable('+60 seconds'))->getTimestamp());

        $this->rateLimiter->expects($this->once())->method('ensureAccepted')->willThrowException($rateLimitException);

        $this->expectExceptionObject(McpException::throttled($rateLimitException->getWaitTime(), $rateLimitException));

        $this->mcpRateLimiter->enforceForStoreApi(new Request());
    }

    public function testEnforceForAdminApiSkipsInitializedNotification(): void
    {
        $this->rateLimiter->expects($this->never())->method('ensureAccepted');

        $this->mcpRateLimiter->enforceForAdminApi($this->jsonRpcRequest('notifications/initialized', id: null));
    }

    public function testEnforceForStoreApiSkipsInitializedNotification(): void
    {
        $this->rateLimiter->expects($this->never())->method('ensureAccepted');

        $this->mcpRateLimiter->enforceForStoreApi($this->jsonRpcRequest('notifications/initialized', id: null));
    }

    public function testEnforceForAdminApiStillLimitsInitialize(): void
    {
        $this->rateLimiter->expects($this->once())
            ->method('ensureAccepted')
            ->with(RateLimiter::MCP_ADMIN_API, '127.0.0.1');

        $this->mcpRateLimiter->enforceForAdminApi($this->jsonRpcRequest('initialize'));
    }

    public function testEnforceForStoreApiStillLimitsInitialize(): void
    {
        $this->rateLimiter->expects($this->once())
            ->method('ensureAccepted')
            ->with(RateLimiter::MCP_STORE_API, '127.0.0.1');

        $this->mcpRateLimiter->enforceForStoreApi($this->jsonRpcRequest('initialize'));
    }

    public function testEnforceForAdminApiSkipsInitializedOnlyBatch(): void
    {
        $this->rateLimiter->expects($this->never())->method('ensureAccepted');

        $request = Request::create('/api/_mcp', 'POST', content: json_encode([
            ['jsonrpc' => '2.0', 'method' => 'notifications/initialized', 'params' => []],
            ['jsonrpc' => '2.0', 'method' => 'notifications/initialized', 'params' => []],
        ], \JSON_THROW_ON_ERROR));

        $this->mcpRateLimiter->enforceForAdminApi($request);
    }

    public function testEnforceForAdminApiStillLimitsInitializeOnlyBatch(): void
    {
        $this->rateLimiter->expects($this->once())
            ->method('ensureAccepted')
            ->with(RateLimiter::MCP_ADMIN_API, '127.0.0.1');

        $request = Request::create('/api/_mcp', 'POST', content: json_encode([
            ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize', 'params' => []],
            ['jsonrpc' => '2.0', 'id' => 2, 'method' => 'initialize', 'params' => []],
        ], \JSON_THROW_ON_ERROR));

        $this->mcpRateLimiter->enforceForAdminApi($request);
    }

    public function testEnforceForAdminApiStillLimitsBatchContainingInitializeAndInitialized(): void
    {
        $this->rateLimiter->expects($this->once())
            ->method('ensureAccepted')
            ->with(RateLimiter::MCP_ADMIN_API, '127.0.0.1');

        $request = Request::create('/api/_mcp', 'POST', content: json_encode([
            ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize', 'params' => []],
            ['jsonrpc' => '2.0', 'method' => 'notifications/initialized', 'params' => []],
        ], \JSON_THROW_ON_ERROR));

        $this->mcpRateLimiter->enforceForAdminApi($request);
    }

    public function testEnforceForAdminApiStillLimitsMixedBatchWithInitialized(): void
    {
        $this->rateLimiter->expects($this->once())
            ->method('ensureAccepted')
            ->with(RateLimiter::MCP_ADMIN_API, '127.0.0.1');

        $request = Request::create('/api/_mcp', 'POST', content: json_encode([
            ['jsonrpc' => '2.0', 'method' => 'notifications/initialized', 'params' => []],
            ['jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/list', 'params' => []],
        ], \JSON_THROW_ON_ERROR));

        $this->mcpRateLimiter->enforceForAdminApi($request);
    }

    public function testEnforceForAdminApiStillLimitsToolsList(): void
    {
        $this->rateLimiter->expects($this->once())
            ->method('ensureAccepted')
            ->with(RateLimiter::MCP_ADMIN_API, '127.0.0.1');

        $this->mcpRateLimiter->enforceForAdminApi($this->jsonRpcRequest('tools/list', id: 2));
    }

    public function testEnforceForAdminApiStillLimitsInvalidJson(): void
    {
        $this->rateLimiter->expects($this->once())
            ->method('ensureAccepted')
            ->with(RateLimiter::MCP_ADMIN_API, '127.0.0.1');

        $request = Request::create('/api/_mcp', 'POST', content: '{not-json');

        $this->mcpRateLimiter->enforceForAdminApi($request);
    }

    public function testEnforceForAdminApiStillLimitsGetRequests(): void
    {
        $this->rateLimiter->expects($this->once())
            ->method('ensureAccepted')
            ->with(RateLimiter::MCP_ADMIN_API, '127.0.0.1');

        $this->mcpRateLimiter->enforceForAdminApi(Request::create('/api/_mcp', 'GET'));
    }

    /**
     * Reproduces #18906: after time_backoff accepts one post-wait request,
     * initialize consumes that slot; the mandatory notifications/initialized
     * follow-up is exempt so the handshake can finish. tools/list still counts.
     */
    public function testPostBackoffHandshakeSequenceCountsInitializeButExemptsInitialized(): void
    {
        $accepted = [];
        $this->rateLimiter->expects($this->exactly(2))
            ->method('ensureAccepted')
            ->willReturnCallback(static function (string $route, string $key) use (&$accepted): void {
                $accepted[] = [$route, $key];
            });

        $tokenRequest = static function (string $method, ?int $id = null): Request {
            $payload = ['jsonrpc' => '2.0', 'method' => $method, 'params' => []];
            if ($id !== null) {
                $payload['id'] = $id;
            }

            $request = Request::create('/api/_mcp', 'POST', content: json_encode($payload, \JSON_THROW_ON_ERROR));
            $request->attributes->set(PlatformRequest::ATTRIBUTE_OAUTH_ACCESS_TOKEN_ID, 'token-after-backoff');

            return $request;
        };

        // After backoff elapsed: initialize consumes the one accepted slot.
        $this->mcpRateLimiter->enforceForAdminApi($tokenRequest('initialize', 1));
        // Immediate notifications/initialized must not be throttled (exempt).
        $this->mcpRateLimiter->enforceForAdminApi($tokenRequest('notifications/initialized'));
        // First real protocol work still draws from the shared bucket.
        $this->mcpRateLimiter->enforceForAdminApi($tokenRequest('tools/list', 2));

        static::assertSame([
            [RateLimiter::MCP_ADMIN_API, 'token-after-backoff'],
            [RateLimiter::MCP_ADMIN_API, 'token-after-backoff'],
        ], $accepted);
    }

    private function jsonRpcRequest(string $method, ?int $id = 1): Request
    {
        $payload = [
            'jsonrpc' => '2.0',
            'method' => $method,
            'params' => [],
        ];
        if ($id !== null) {
            $payload['id'] = $id;
        }

        return Request::create('/api/_mcp', 'POST', content: json_encode($payload, \JSON_THROW_ON_ERROR));
    }
}
