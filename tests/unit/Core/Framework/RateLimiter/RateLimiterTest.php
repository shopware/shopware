<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\RateLimiter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\RateLimiter\RateLimiter;
use Shopware\Core\Framework\RateLimiter\RateLimiterException;
use Shopware\Core\Framework\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Policy\FixedWindowLimiter;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

/**
 * @internal
 */
#[CoversClass(RateLimiter::class)]
class RateLimiterTest extends TestCase
{
    public function testExceptionIsThrownForUnknownRoute(): void
    {
        static::expectExceptionObject(RateLimiterException::factoryNotFound('some-route'));
        $rateLimiter = new RateLimiter();
        $rateLimiter->ensureAccepted('some-route', 'some-key');
    }

    public function testExceptionIsThrownWhenLimitExceeded(): void
    {
        $e = RateLimiterException::limitExceeded(time() + 60);
        static::expectExceptionObject($e);

        $limiter = new FixedWindowLimiter('test', 1, new \DateInterval('PT1M'), new InMemoryStorage());

        $factory = $this->createMock(RateLimiterFactory::class);
        $factory->method('create')->with('some-key')->willReturn($limiter);

        $rateLimiter = new RateLimiter();
        $rateLimiter->registerLimiterFactory('some-route', $factory);
        $rateLimiter->ensureAccepted('some-route', 'some-key');
        $rateLimiter->ensureAccepted('some-route', 'some-key');
    }
}
