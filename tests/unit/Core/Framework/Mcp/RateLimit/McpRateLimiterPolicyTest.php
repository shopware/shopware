<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp\RateLimit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\McpException;
use Shopware\Core\Framework\Mcp\RateLimit\McpRateLimiter;
use Shopware\Core\Framework\RateLimiter\RateLimiter;
use Shopware\Core\Framework\RateLimiter\RateLimiterFactory;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Bridge\PhpUnit\ClockMock;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\Policy\SlidingWindow;
use Symfony\Component\RateLimiter\Policy\SlidingWindowLimiter;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

/**
 * Runs a real limiter, so the policy is covered and not only the key McpRateLimiter derives. The
 * shipped limits are covered by the integration test of the same name.
 *
 * @internal
 */
#[Package('framework')]
#[CoversClass(McpRateLimiter::class)]
class McpRateLimiterPolicyTest extends TestCase
{
    private MockClock $clock;

    protected function setUp(): void
    {
        // Symfony's own policies read microtime(), Shopware's read the injected clock; both move together.
        ClockMock::register(SlidingWindow::class);
        ClockMock::register(SlidingWindowLimiter::class);
        ClockMock::withClockMock(1_790_000_000.0);
        $this->clock = new MockClock('@1790000000');
    }

    protected function tearDown(): void
    {
        ClockMock::withClockMock(false);
    }

    /**
     * @return iterable<string, array{string, \Closure(McpRateLimiter, Request): void, \Closure(): Request}>
     */
    public static function endpointProvider(): iterable
    {
        yield 'Admin API' => [
            RateLimiter::MCP_ADMIN_API,
            static fn (McpRateLimiter $limiter, Request $request) => $limiter->enforceForAdminApi($request),
            static function (): Request {
                $request = new Request();
                $request->attributes->set(PlatformRequest::ATTRIBUTE_OAUTH_ACCESS_TOKEN_ID, 'token-id');

                return $request;
            },
        ];

        yield 'Store API' => [
            RateLimiter::MCP_STORE_API,
            static fn (McpRateLimiter $limiter, Request $request) => $limiter->enforceForStoreApi($request),
            static fn (): Request => new Request(server: ['REMOTE_ADDR' => '192.0.2.1']),
        ];
    }

    /**
     * time_backoff accepted a single request after the pause and throttled the next one (#18906).
     *
     * @param \Closure(McpRateLimiter, Request): void $enforce
     * @param \Closure(): Request $request
     */
    #[DataProvider('endpointProvider')]
    public function testTheFullLimitIsAvailableAgainAfterThePause(string $route, \Closure $enforce, \Closure $request): void
    {
        $limiter = $this->limiter($route, ['enabled' => true, 'policy' => 'sliding_window', 'limit' => 2, 'interval' => '1 minute']);

        static::assertSame(2, $this->acceptedUntilThrottled($limiter, $enforce, $request));

        // A sliding window is empty again after two intervals without requests.
        $this->sleep(120);

        static::assertSame(2, $this->acceptedUntilThrottled($limiter, $enforce, $request));
    }

    /**
     * @param array{enabled: bool, policy: string, limit: int, interval: string} $config
     */
    private function limiter(string $route, array $config): McpRateLimiter
    {
        $rateLimiter = new RateLimiter();
        $rateLimiter->registerLimiterFactory($route, new RateLimiterFactory(
            [...$config, 'id' => $route],
            new InMemoryStorage(),
            static::createStub(SystemConfigService::class),
            $this->clock,
        ));

        return new McpRateLimiter($rateLimiter);
    }

    /**
     * @param \Closure(McpRateLimiter, Request): void $enforce
     * @param \Closure(): Request $request
     */
    private function acceptedUntilThrottled(McpRateLimiter $limiter, \Closure $enforce, \Closure $request): int
    {
        $accepted = 0;

        try {
            while ($accepted < 10) {
                $enforce($limiter, $request());
                ++$accepted;
            }
        } catch (McpException) {
        }

        return $accepted;
    }

    private function sleep(int $seconds): void
    {
        $this->clock->sleep($seconds);
        ClockMock::sleep($seconds);
    }
}
