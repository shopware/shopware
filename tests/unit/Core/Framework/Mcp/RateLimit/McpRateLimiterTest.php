<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp\RateLimit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
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

    public function testEnforceForStoreApiUsesSalesChannelContextKey(): void
    {
        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannelContext->method('getSalesChannelId')->willReturn('sales-channel-id');
        $salesChannelContext->method('getToken')->willReturn('context-token');

        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT, $salesChannelContext);

        $this->rateLimiter->expects($this->once())
            ->method('ensureAccepted')
            ->with(RateLimiter::MCP_STORE_API, 'sales-channel-id-context-token');

        $this->mcpRateLimiter->enforceForStoreApi($request);
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
    public function testEnforceForStoreApiFallsBackWithoutContext(?string $remoteAddr, string $expectedKey): void
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

        $this->rateLimiter->method('ensureAccepted')->willThrowException($rateLimitException);

        $this->expectExceptionObject(McpException::throttled($rateLimitException->getWaitTime(), $rateLimitException));

        $this->mcpRateLimiter->enforceForAdminApi(new Request());
    }

    public function testEnforceForStoreApiTranslatesRateLimitException(): void
    {
        $rateLimitException = new RateLimitExceededException((new \DateTimeImmutable('+60 seconds'))->getTimestamp());

        $this->rateLimiter->method('ensureAccepted')->willThrowException($rateLimitException);

        $this->expectExceptionObject(McpException::throttled($rateLimitException->getWaitTime(), $rateLimitException));

        $this->mcpRateLimiter->enforceForStoreApi(new Request());
    }
}
