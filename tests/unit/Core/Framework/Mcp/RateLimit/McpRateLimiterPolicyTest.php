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
use Symfony\Component\Yaml\Yaml;

/**
 * Runs the MCP limits as shipped in shopware.yaml through a real limiter, so the policy itself is
 * covered and not only the key McpRateLimiter derives.
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
     * @param \Closure(McpRateLimiter, Request): void $enforce
     * @param \Closure(): Request $request
     */
    #[DataProvider('endpointProvider')]
    public function testAClientThatUsedUpItsLimitIsServedAgainAfterAPause(string $route, \Closure $enforce, \Closure $request): void
    {
        $config = $this->shippedConfig($route);
        $limiter = $this->limiter($route, $config);
        $limit = $config['limit'] ?? $config['limits'][0]['limit'] ?? 0;

        for ($i = 0; $i < $limit; ++$i) {
            $enforce($limiter, $request());
        }

        // A sliding window frees capacity gradually; after two intervals without requests it is empty.
        // time_backoff accepted one request here and throttled the next for another interval (#18906).
        $this->sleep(120);

        // A new handshake followed by the first real call.
        $enforce($limiter, $request());
        $enforce($limiter, $request());
        $enforce($limiter, $request());

        $this->expectNotToPerformAssertions();
    }

    /**
     * @param \Closure(McpRateLimiter, Request): void $enforce
     * @param \Closure(): Request $request
     */
    #[DataProvider('endpointProvider')]
    public function testAClientOverItsLimitIsThrottled(string $route, \Closure $enforce, \Closure $request): void
    {
        $config = $this->shippedConfig($route);
        $limiter = $this->limiter($route, $config);
        $limit = $config['limit'] ?? $config['limits'][0]['limit'] ?? 0;

        for ($i = 0; $i < $limit; ++$i) {
            $enforce($limiter, $request());
        }

        $this->expectException(McpException::class);

        $enforce($limiter, $request());
    }

    /**
     * @param array{id: string, enabled: bool, policy: string, limit?: int, interval?: string, reset?: string, limits?: list<array{limit: int, interval: string}>} $config
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
     * @return array{id: string, enabled: bool, policy: string, limit?: int, interval?: string, reset?: string, limits?: list<array{limit: int, interval: string}>}
     */
    private function shippedConfig(string $route): array
    {
        /** @var array{shopware: array{api: array{rate_limiter: array<string, array{id: string, enabled: bool, policy: string, limit?: int, interval?: string, reset?: string, limits?: list<array{limit: int, interval: string}>}>}}} $config */
        $config = Yaml::parseFile(\dirname(__DIR__, 6) . '/src/Core/Framework/Resources/config/packages/shopware.yaml');

        return $config['shopware']['api']['rate_limiter'][$route];
    }

    private function sleep(int $seconds): void
    {
        $this->clock->sleep($seconds);
        ClockMock::sleep($seconds);
    }
}
