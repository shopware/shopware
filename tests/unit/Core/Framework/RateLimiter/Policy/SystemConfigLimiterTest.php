<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\RateLimiter\Policy;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\RateLimiter\Policy\SystemConfigLimiter;
use Shopware\Core\Framework\RateLimiter\Policy\TimeBackoff;
use Shopware\Core\Framework\RateLimiter\RateLimiterFactory;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\RateLimiter\LimiterInterface;
use Symfony\Component\RateLimiter\Storage\CacheStorage;

/**
 * @internal
 *
 * @phpstan-import-type RateLimiterConfig from RateLimiterFactory
 */
#[Package('framework')]
#[CoversClass(SystemConfigLimiter::class)]
class SystemConfigLimiterTest extends TestCase
{
    /**
     * @var array<string, TimeBackoff>
     */
    private array $cache;

    /**
     * @var RateLimiterConfig
     */
    private array $config;

    protected function setUp(): void
    {
        $this->config = [
            'enabled' => true,
            'id' => 'test_limit',
            'policy' => 'system_config',
            'reset' => '5 minutes',
            'limits' => [
                [
                    'domain' => 'test.limit',
                    'interval' => '10 seconds',
                ],
            ],
        ];
    }

    public function testConsume(): void
    {
        $limiter = $this->createLimiter(['test.limit' => 3]);

        $limit = $limiter->consume();
        static::assertTrue($limit->isAccepted());

        $limiter->reset();

        $limit = $limiter->consume(3);
        static::assertTrue($limit->isAccepted());

        $limit = $limiter->consume();
        static::assertFalse($limit->isAccepted());
    }

    public function testConsumeSequentiallyAllowsTheConfiguredLimit(): void
    {
        $limiter = $this->createLimiter(['test.limit' => 3]);

        static::assertTrue($limiter->consume()->isAccepted());
        static::assertTrue($limiter->consume()->isAccepted());
        static::assertTrue($limiter->consume()->isAccepted());
        static::assertFalse($limiter->consume()->isAccepted());
    }

    public function testNoLimitWithZero(): void
    {
        $limit = $this->createLimiter(['test.limit' => 0])->consume(100);
        static::assertTrue($limit->isAccepted());
    }

    /**
     * @param array<string, int> $domainLimits
     */
    private function createLimiter(array $domainLimits): LimiterInterface
    {
        static::assertArrayHasKey('limits', $this->config);
        static::assertIsArray($this->config['limits']);

        $systemConfig = $this->createMock(SystemConfigService::class);
        $systemConfig
            ->expects($this->once())
            ->method('getInt')
            ->willReturnCallback(
                static fn (string $domain) => $domainLimits[$domain] ?? 0
            );

        $cacheStorage = $this->createMock(CacheStorage::class);
        $cacheStorage
            ->expects($this->atLeast(1))
            ->method('fetch')
            ->willReturnCallback(
                fn (string $id) => $this->cache[$id] ?? null
            );
        $cacheStorage
            ->expects($this->atLeast(1))
            ->method('save')
            ->willReturnCallback(
                fn (TimeBackoff $timeBackoff) => $this->cache[$timeBackoff->getId()] = $timeBackoff
            );
        $cacheStorage
            ->method('delete')
            ->willReturnCallback(
                function (string $id): void {
                    unset($this->cache[$id]);
                }
            );

        $factory = new RateLimiterFactory(
            $this->config,
            $cacheStorage,
            $systemConfig,
            new MockClock(),
            static::createStub(LockFactory::class),
        );

        return $factory->create('example');
    }
}
