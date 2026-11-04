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
    private const USER_ID = '0190a1b2c3d4e5f6a7b8c9d0e1f2a3b4';
    private const INTEGRATION_A = 'SWIAINTEGRATIONAAAAAAAAAAAAAA';
    private const INTEGRATION_B = 'SWIAINTEGRATIONBBBBBBBBBBBBBB';

    private RateLimiter&MockObject $rateLimiter;

    private McpRateLimiter $mcpRateLimiter;

    protected function setUp(): void
    {
        $this->rateLimiter = $this->createMock(RateLimiter::class);
        $this->mcpRateLimiter = new McpRateLimiter($this->rateLimiter);
    }

    /**
     * @return iterable<string, array{array<string, string>, ?string, string}>
     */
    public static function adminApiKeyProvider(): iterable
    {
        yield 'keyed by user' => [
            [PlatformRequest::ATTRIBUTE_OAUTH_ACCESS_TOKEN_ID => 'jti-1', PlatformRequest::ATTRIBUTE_OAUTH_USER_ID => self::USER_ID],
            '192.168.1.1',
            'user-' . self::USER_ID,
        ];
        yield 'keyed by integration' => [
            [PlatformRequest::ATTRIBUTE_OAUTH_ACCESS_TOKEN_ID => 'mcp-' . self::INTEGRATION_A, PlatformRequest::ATTRIBUTE_OAUTH_CLIENT_ID => self::INTEGRATION_A],
            '192.168.1.1',
            'integration-' . self::INTEGRATION_A,
        ];
        yield 'falls back to client IP without principal' => [
            [PlatformRequest::ATTRIBUTE_OAUTH_ACCESS_TOKEN_ID => 'jti-1'],
            '192.168.1.1',
            '192.168.1.1',
        ];
        yield 'falls back to unknown' => [[], null, 'unknown'];
    }

    /**
     * @param array<string, string> $attributes
     */
    #[DataProvider('adminApiKeyProvider')]
    public function testEnforceForAdminApiUsesExpectedKey(array $attributes, ?string $remoteAddr, string $expectedKey): void
    {
        $request = new Request(attributes: $attributes);
        if ($remoteAddr !== null) {
            $request->server->set('REMOTE_ADDR', $remoteAddr);
        }

        $this->rateLimiter->expects($this->once())
            ->method('ensureAccepted')
            ->with(RateLimiter::MCP_ADMIN_API, $expectedKey);

        $this->mcpRateLimiter->enforceForAdminApi($request);
    }

    public function testRefreshedTokenKeepsTheBucketAndIntegrationsHaveTheirOwn(): void
    {
        $keys = [];
        $this->rateLimiter->expects($this->exactly(4))
            ->method('ensureAccepted')
            ->willReturnCallback(static function (string $route, string $key) use (&$keys): void {
                $keys[] = $key;
            });

        $this->mcpRateLimiter->enforceForAdminApi($this->integrationRequest(self::INTEGRATION_A, 'jti-1'));
        $this->mcpRateLimiter->enforceForAdminApi($this->integrationRequest(self::INTEGRATION_A, 'jti-2'));
        $this->mcpRateLimiter->enforceForAdminApi($this->integrationRequest(self::INTEGRATION_B, 'jti-3'));
        $this->mcpRateLimiter->enforceForAdminApi($this->integrationRequest(self::INTEGRATION_B, 'mcp-' . self::INTEGRATION_B));

        static::assertSame($keys[0], $keys[1], 'a new token of the same integration must keep its bucket');
        static::assertSame($keys[2], $keys[3], 'access-key headers and a bearer token of one integration share a bucket');
        static::assertNotSame($keys[0], $keys[2], 'two integrations must not share a bucket');
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

    private function integrationRequest(string $accessKey, string $tokenId): Request
    {
        return new Request(attributes: [
            PlatformRequest::ATTRIBUTE_OAUTH_ACCESS_TOKEN_ID => $tokenId,
            PlatformRequest::ATTRIBUTE_OAUTH_CLIENT_ID => $accessKey,
        ]);
    }
}
