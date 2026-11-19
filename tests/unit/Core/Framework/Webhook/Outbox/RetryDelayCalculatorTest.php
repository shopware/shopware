<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Webhook\Outbox;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Webhook\Outbox\RetryDelayCalculator;
use Symfony\Component\Clock\MockClock;

/**
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
     * @return iterable<string, array{string, int, int}>
     */
    public static function retryAfterProvider(): iterable
    {
        yield 'delta seconds override the schedule' => ['120', 1, 120];
        yield 'HTTP date overrides the schedule' => ['Wed, 15 Apr 2026 12:02:00 GMT', 1, 120];
        yield 'lower bound is honoured' => ['1', 1, 1];
        yield 'upper bound is honoured' => ['14400', 1, 14400];
        yield 'malformed value falls back to the schedule' => ['invalid', 1, 5];
        yield 'value below the lower bound falls back to the schedule' => ['0', 1, 5];
        yield 'value above the upper bound preserves the schedule tier' => ['14401', 3, 300];
    }

    #[DataProvider('retryAfterProvider')]
    public function testComputeNextRetryAtHandlesRetryAfter(string $retryAfter, int $executionCount, int $expectedDelay): void
    {
        $now = new \DateTimeImmutable('2026-04-15 12:00:00');
        $calculator = new RetryDelayCalculator(new MockClock($now));

        static::assertEquals(
            $now->modify(\sprintf('+%d seconds', $expectedDelay)),
            $calculator->computeNextRetryAt($executionCount, $retryAfter)
        );
    }
}
