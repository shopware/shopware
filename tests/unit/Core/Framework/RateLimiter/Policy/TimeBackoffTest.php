<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\RateLimiter\Policy;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\RateLimiter\Policy\TimeBackoff;
use Shopware\Core\Framework\RateLimiter\RateLimiterException;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(TimeBackoff::class)]
class TimeBackoffTest extends TestCase
{
    /**
     * @param list<array{limit: int, interval: string}> $limits
     */
    #[DataProvider('limitsProvider')]
    public function testShouldThrottleOnlyAboveTheConfiguredLimit(array $limits, int $allowed): void
    {
        $timer = 1000000;
        $backoff = new TimeBackoff('test', $limits, $timer);

        $now = $timer + 5;

        static::assertFalse($backoff->shouldThrottle($allowed, $now));
        static::assertTrue($backoff->shouldThrottle($allowed + 1, $now));
    }

    public function testAvailableAttemptsAndThrottleAgreeAtTheLimit(): void
    {
        $timer = 1000000;
        $backoff = new TimeBackoff('test', [['limit' => 3, 'interval' => '10 minutes']], $timer);

        $backoff->setAttempts(2);

        static::assertSame(1, $backoff->getAvailableAttempts($timer + 5));
        static::assertFalse($backoff->shouldThrottle(3, $timer + 5));
    }

    public function testTopTierBoundaryIsGovernedByThePrecedingTier(): void
    {
        $timer = 1000000;
        $backoff = new TimeBackoff('test', [['limit' => 10, 'interval' => '10 seconds'], ['limit' => 15, 'interval' => '30 seconds'], ['limit' => 20, 'interval' => '60 seconds']], $timer);

        static::assertTrue($backoff->shouldThrottle(20, $timer + 29));
        static::assertFalse($backoff->shouldThrottle(20, $timer + 30));
        static::assertTrue($backoff->shouldThrottle(21, $timer + 45));
    }

    public function testThrowsExceptionOnInvalidLimits(): void
    {
        $backoff = new TimeBackoff('test', [
            [
                'limit' => 3,
                'interval' => '10 seconds',
            ],
            [
                'limit' => 5,
                'interval' => '30 seconds',
            ],
        ]);

        $stringLimits = new \ReflectionProperty(TimeBackoff::class, 'stringLimits');
        $stringLimits->setValue($backoff, 'invalid');

        static::expectExceptionObject(RateLimiterException::backoffUnserializationFailed(TimeBackoff::class));
        $backoff->__wakeup();
    }

    /**
     * @return \Generator<string, array{list<array{limit: int, interval: string}>, int}>
     */
    public static function limitsProvider(): \Generator
    {
        yield 'single entry allows the configured number of attempts' => [
            [['limit' => 3, 'interval' => '10 minutes']],
            3,
        ];

        yield 'single entry with limit 1 allows one attempt' => [
            [['limit' => 1, 'interval' => '10 minutes']],
            1,
        ];

        yield 'multi entry still allows the lowest tier before the first backoff' => [
            [['limit' => 10, 'interval' => '10 seconds'], ['limit' => 15, 'interval' => '30 seconds'], ['limit' => 20, 'interval' => '60 seconds']],
            10,
        ];
    }
}
