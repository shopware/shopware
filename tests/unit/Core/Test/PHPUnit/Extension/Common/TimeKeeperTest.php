<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Test\PHPUnit\Extension\Common;

use PHPUnit\Event\Telemetry\HRTime;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Test\PHPUnit\Extension\Common\TimeKeeper;

/**
 * @internal
 */
#[CoversClass(TimeKeeper::class)]
class TimeKeeperTest extends TestCase
{
    public function testStartAndStop(): void
    {
        $timeKeeper = new TimeKeeper();
        $testIdentifier = 'testIdentifier';
        $startedTime = HRTime::fromSecondsAndNanoseconds(1, 0);
        $stoppedTime = HRTime::fromSecondsAndNanoseconds(2, 0);

        $timeKeeper->start($testIdentifier, $startedTime);

        $duration = $timeKeeper->stop($testIdentifier, $stoppedTime);

        static::assertEquals(1, $duration->seconds());
    }

    public function testStopWithoutStartShouldReturnZeroDuration(): void
    {
        $timeKeeper = new TimeKeeper();
        $testIdentifier = 'nonExistentTest';
        $stoppedTime = HRTime::fromSecondsAndNanoseconds(1, 0);

        $duration = $timeKeeper->stop($testIdentifier, $stoppedTime);

        static::assertEquals(0, $duration->seconds());
    }
}
