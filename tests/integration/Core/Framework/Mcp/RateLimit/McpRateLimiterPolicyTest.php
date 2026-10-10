<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Mcp\RateLimit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\McpException;
use Shopware\Core\Framework\Mcp\RateLimit\McpRateLimiter;
use Shopware\Core\Framework\RateLimiter\RateLimiter;
use Shopware\Core\Framework\RateLimiter\RateLimiterFactory;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Bridge\PhpUnit\ClockMock;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\Policy\SlidingWindow;
use Symfony\Component\RateLimiter\Policy\SlidingWindowLimiter;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

/**
 * Runs the configured MCP limits through a real limiter. The test environment disables rate limiting,
 * so the configuration is read here and built with `enabled` forced on.
 *
 * @internal
 */
#[Package('framework')]
class McpRateLimiterPolicyTest extends TestCase
{
    use KernelTestBehaviour;

    private MockClock $clock;

    protected function setUp(): void
    {
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
     * A busy client must get its full limit back after a pause; time_backoff gave it one request (#18906).
     *
     * @param \Closure(McpRateLimiter, Request): void $enforce
     * @param \Closure(): Request $request
     */
    #[DataProvider('endpointProvider')]
    public function testTheConfiguredLimitIsAvailableAgainAfterThePause(string $route, \Closure $enforce, \Closure $request): void
    {
        /** @var array<string, array{enabled: bool, policy: string, limit?: int, interval?: string, limits?: list<array{limit: int, interval: string}>}> $config */
        $config = static::getContainer()->getParameter('shopware.api.rate_limiter');
        $limiter = $this->limiter($route, $config[$route]);
        // `limits` is the shape of time_backoff, so this also runs against the configuration it replaced.
        $limit = $config[$route]['limit'] ?? $config[$route]['limits'][0]['limit'] ?? 0;

        static::assertGreaterThan(0, $limit);
        static::assertSame($limit, $this->acceptedUntilThrottled($limiter, $enforce, $request, $limit));

        $this->clock->sleep(120);
        ClockMock::sleep(120);

        static::assertSame($limit, $this->acceptedUntilThrottled($limiter, $enforce, $request, $limit));
    }

    /**
     * @param array{enabled: bool, policy: string, limit?: int, interval?: string, limits?: list<array{limit: int, interval: string}>} $config
     */
    private function limiter(string $route, array $config): McpRateLimiter
    {
        $rateLimiter = new RateLimiter();
        $rateLimiter->registerLimiterFactory($route, new RateLimiterFactory(
            [...$config, 'id' => $route, 'enabled' => true],
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
    private function acceptedUntilThrottled(McpRateLimiter $limiter, \Closure $enforce, \Closure $request, int $limit): int
    {
        $accepted = 0;

        try {
            while ($accepted <= $limit) {
                $enforce($limiter, $request());
                ++$accepted;
            }
        } catch (McpException) {
        }

        return $accepted;
    }
}
