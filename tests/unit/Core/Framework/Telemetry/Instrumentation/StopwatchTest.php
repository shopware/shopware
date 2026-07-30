<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Telemetry\Instrumentation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Telemetry\Instrumentation\Stopwatch;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Stopwatch::class)]
class StopwatchTest extends TestCase
{
    public function testGetElapsedMsReturnsNonNegativeDuration(): void
    {
        $stopwatch = Stopwatch::start();

        static::assertGreaterThanOrEqual(0.0, $stopwatch->getElapsedMs());
    }

    public function testElapsedIsMonotonicNonDecreasing(): void
    {
        $stopwatch = Stopwatch::start();

        $first = $stopwatch->getElapsedMs();
        $second = $stopwatch->getElapsedMs();

        // hrtime is monotonic, so a later read can never report less elapsed time than an earlier one.
        static::assertGreaterThanOrEqual($first, $second);
    }
}
