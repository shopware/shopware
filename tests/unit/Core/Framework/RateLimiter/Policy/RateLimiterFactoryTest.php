<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\RateLimiter\Policy;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\RateLimiter\Policy\TimeBackoffLimiter;
use Shopware\Core\Framework\RateLimiter\RateLimiterFactory;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\RateLimiter\Policy\SlidingWindowLimiter;
use Symfony\Component\RateLimiter\Policy\TokenBucketLimiter;
use Symfony\Component\RateLimiter\Storage\StorageInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(RateLimiterFactory::class)]
class RateLimiterFactoryTest extends TestCase
{
    public function testFactoryShouldReturnCustomPolicy(): void
    {
        $factory = new RateLimiterFactory(
            [
                'enabled' => true,
                'id' => 'test_limiter',
                'policy' => 'time_backoff',
                'reset' => '1 hour',
                'limits' => [
                    [
                        'limit' => 3,
                        'interval' => '10 seconds',
                    ],
                    [
                        'limit' => 5,
                        'interval' => '30 seconds',
                    ],
                ],
            ],
            static::createStub(StorageInterface::class),
            static::createStub(SystemConfigService::class),
            new MockClock(),
            static::createStub(LockFactory::class),
        );

        static::assertInstanceOf(TimeBackoffLimiter::class, $factory->create('example'));
    }

    public function testFactoryShouldUseSymfonyFactory(): void
    {
        $factory = new RateLimiterFactory(
            [
                'enabled' => true,
                'id' => 'test_limiter',
                'policy' => 'token_bucket',
                'limit' => 3,
                'rate' => ['interval' => '60 seconds'],
            ],
            static::createStub(StorageInterface::class),
            static::createStub(SystemConfigService::class),
            new MockClock(),
            static::createStub(LockFactory::class),
        );

        static::assertInstanceOf(TokenBucketLimiter::class, $factory->create('example'));
    }

    public function testFactoryShouldUseSymfonyFactoryOverrideDefaultConfig(): void
    {
        $factory = new RateLimiterFactory(
            [
                'enabled' => true,
                'id' => 'test_limiter',
                'policy' => 'token_bucket',
                'reset' => '1 hour',
                'limits' => [
                    [
                        'limit' => 3,
                        'interval' => '10 seconds',
                    ],
                    [
                        'limit' => 5,
                        'interval' => '30 seconds',
                    ],
                ],
                'limit' => 3,
                'rate' => ['interval' => '60 seconds'],
            ],
            static::createStub(StorageInterface::class),
            static::createStub(SystemConfigService::class),
            new MockClock(),
            static::createStub(LockFactory::class),
        );

        static::assertInstanceOf(TokenBucketLimiter::class, $factory->create('example'));
    }

    public function testFactoryShouldKeepConfigUntouched(): void
    {
        $factory = new RateLimiterFactory(
            [
                'enabled' => true,
                'id' => 'test_limiter',
                'policy' => 'sliding_window',
                'limit' => 1,
                'interval' => '15 seconds',
            ],
            static::createStub(StorageInterface::class),
            static::createStub(SystemConfigService::class),
            new MockClock(),
            static::createStub(LockFactory::class),
        );
        static::assertInstanceOf(SlidingWindowLimiter::class, $factory->create('example_1'));
        static::assertInstanceOf(SlidingWindowLimiter::class, $factory->create('example_2'));
    }
}
