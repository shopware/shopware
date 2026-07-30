<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Telemetry\Instrumentation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Telemetry\Instrumentation\ElapsedTimer;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ElapsedTimer::class)]
class ElapsedTimerTest extends TestCase
{
    public function testGetElapsedMsReturnsNonNegativeDuration(): void
    {
        $timer = ElapsedTimer::start();

        static::assertGreaterThanOrEqual(0.0, $timer->getElapsedMs());
    }

    public function testElapsedIsMonotonicNonDecreasing(): void
    {
        $timer = ElapsedTimer::start();

        $first = $timer->getElapsedMs();
        $second = $timer->getElapsedMs();

        // hrtime is monotonic, so a later read can never report less elapsed time than an earlier one.
        static::assertGreaterThanOrEqual($first, $second);
    }
}
