<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Webhook\Outbox;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Webhook\Outbox\RetryDelayCalculator;
use Symfony\Component\Clock\MockClock;

/**
 * Locks down the per-attempt retry backoff: the fixed delay table (5s … 4h) clamped at both ends,
 * and the 429 Retry-After override, honoured only within [1s, 4h] — anything malformed or
 * out of range falls back to the table. A wrong delay here either hammers a struggling endpoint
 * or stalls deliveries far longer than intended.
 *
 * @internal
 */
#[Package('framework')]
#[CoversClass(RetryDelayCalculator::class)]
class RetryDelayCalculatorTest extends TestCase
{
    /**
     * @return iterable<string, array{int, int}>
     */
    public static function executionCountProvider(): iterable
    {
        yield 'execution count 1 => 5s' => [1, 5];
        yield 'execution count 2 => 30s' => [2, 30];
        yield 'execution count 3 => 300s' => [3, 300];
        yield 'execution count 4 => 1800s' => [4, 1800];
        yield 'execution count 5 => 14400s' => [5, 14400];
    }

    #[DataProvider('executionCountProvider')]
    public function testComputeNextRetryAtReturnsCorrectDelay(int $executionCount, int $expectedDelay): void
    {
        $now = new \DateTimeImmutable('2026-04-15 12:00:00');
        $clock = new MockClock($now);

        $calculator = new RetryDelayCalculator($clock);
        \assert($executionCount >= 1);
        $result = $calculator->computeNextRetryAt($executionCount);

        $expected = $now->modify(\sprintf('+%d seconds', $expectedDelay));
        static::assertEquals($expected, $result);
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function executionCountBeyondTableProvider(): iterable
    {
        yield 'execution count 6' => [6];
        yield 'execution count 10' => [10];
        yield 'execution count 100' => [100];
    }

    #[DataProvider('executionCountBeyondTableProvider')]
    public function testComputeNextRetryAtClampsToLastDelayWhenBeyondTableSize(int $executionCount): void
    {
        $now = new \DateTimeImmutable('2026-04-15 12:00:00');
        $clock = new MockClock($now);

        $calculator = new RetryDelayCalculator($clock);
        $result = $calculator->computeNextRetryAt($executionCount);

        $expected = $now->modify('+14400 seconds');
        static::assertEquals($expected, $result);
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function zeroOrNegativeExecutionCountProvider(): iterable
    {
        yield 'execution count 0' => [0];
        yield 'execution count -1' => [-1];
        yield 'execution count -100' => [-100];
    }

    #[DataProvider('zeroOrNegativeExecutionCountProvider')]
    public function testComputeNextRetryAtClampsToFirstDelayWhenZeroOrNegative(int $executionCount): void
    {
        $now = new \DateTimeImmutable('2026-04-15 12:00:00');
        $clock = new MockClock($now);

        $calculator = new RetryDelayCalculator($clock);
        $result = $calculator->computeNextRetryAt($executionCount);

        $expected = $now->modify('+5 seconds');
        static::assertEquals($expected, $result);
    }

    /**
     * @return iterable<string, array{?string, int, string}>
     */
    public static function retryAfterProvider(): iterable
    {
        // A 429's Retry-After (in seconds) within [1 s, 4 h] wins over the schedule.
        yield '120s honoured' => ['120', 1, '+120 seconds'];
        yield 'lower bound 1s honoured' => ['1', 1, '+1 seconds'];
        yield 'upper bound 14400s honoured' => ['14400', 1, '+14400 seconds'];
        yield 'surrounding whitespace tolerated' => ['  90 ', 1, '+90 seconds'];

        // Out-of-range or malformed values fall back to the schedule tier for that attempt.
        yield '0 below the floor → schedule (attempt 1 = 5s)' => ['0', 1, '+5 seconds'];
        yield '14401 above 4h → schedule' => ['14401', 1, '+5 seconds'];
        yield '999999 → schedule' => ['999999', 1, '+5 seconds'];
        yield 'negative → schedule' => ['-5', 1, '+5 seconds'];
        yield 'non-numeric garbage → schedule' => ['soon', 1, '+5 seconds'];
        yield 'empty string → schedule' => ['', 1, '+5 seconds'];
        yield 'null (no header) → schedule' => [null, 1, '+5 seconds'];

        // The schedule fallback still respects the execution-count tier.
        yield 'out-of-range keeps the tier (attempt 3 = 300s)' => ['0', 3, '+300 seconds'];
    }

    #[DataProvider('retryAfterProvider')]
    public function testComputeNextRetryAtHonoursClampedRetryAfter(?string $retryAfter, int $executionCount, string $expectedModifier): void
    {
        $now = new \DateTimeImmutable('2026-04-15 12:00:00');
        $clock = new MockClock($now);

        $calculator = new RetryDelayCalculator($clock);
        $result = $calculator->computeNextRetryAt($executionCount, $retryAfter);

        static::assertEquals($now->modify($expectedModifier), $result);
    }

    public function testComputeNextRetryAtHonoursHttpDateRetryAfterWithinBounds(): void
    {
        $now = new \DateTimeImmutable('2026-04-15 12:00:00', new \DateTimeZone('UTC'));
        $clock = new MockClock($now);

        $calculator = new RetryDelayCalculator($clock);
        $httpDate = $now->modify('+300 seconds')->format(\DateTimeInterface::RFC7231);
        $result = $calculator->computeNextRetryAt(1, $httpDate);

        static::assertEquals($now->modify('+300 seconds'), $result);
    }

    public function testComputeNextRetryAtFallsBackToScheduleForPastHttpDate(): void
    {
        $now = new \DateTimeImmutable('2026-04-15 12:00:00', new \DateTimeZone('UTC'));
        $clock = new MockClock($now);

        $calculator = new RetryDelayCalculator($clock);
        $httpDate = $now->modify('-60 seconds')->format(\DateTimeInterface::RFC7231);
        $result = $calculator->computeNextRetryAt(1, $httpDate);

        static::assertEquals($now->modify('+5 seconds'), $result);
    }
}
