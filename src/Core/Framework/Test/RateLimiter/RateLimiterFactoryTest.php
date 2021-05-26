<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\RateLimiter;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\RateLimiter\Policy\TimeBackoffLimiter;
use Shopware\Core\Framework\RateLimiter\RateLimiterFactory;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\RateLimiter\Policy\TokenBucketLimiter;
use Symfony\Component\RateLimiter\Storage\StorageInterface;

class RateLimiterFactoryTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testFactoryShouldReturnCustomPolicy(): void
    {
        $factory = new RateLimiterFactory(
            [
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
            $this->createMock(StorageInterface::class),
            $this->createMock(LockFactory::class)
        );

        static::assertInstanceOf(TimeBackoffLimiter::class, $factory->create('example'));
    }

    public function testFactoryShouldUseSymfonyFactory(): void
    {
        $factory = new RateLimiterFactory(
            [
                'id' => 'test_limiter',
                'policy' => 'token_bucket',
                'limit' => 3,
                'rate' => ['interval' => '60 seconds'],
            ],
            $this->createMock(StorageInterface::class),
            $this->createMock(LockFactory::class)
        );

        static::assertInstanceOf(TokenBucketLimiter::class, $factory->create('example'));
    }
}
