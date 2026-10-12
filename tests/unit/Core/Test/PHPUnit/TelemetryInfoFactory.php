<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Test\PHPUnit;

use PHPUnit\Event\Telemetry\Duration;
use PHPUnit\Event\Telemetry\GarbageCollectorStatus;
use PHPUnit\Event\Telemetry\HRTime;
use PHPUnit\Event\Telemetry\Info;
use PHPUnit\Event\Telemetry\MemoryUsage;
use PHPUnit\Event\Telemetry\Snapshot;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * Builds the telemetry value PHPUnit attaches to every event, for subscriber tests that need a well-formed
 * event and never read its telemetry. PHPUnit 13 added CPU time to `Snapshot` (4 to 7 constructor arguments)
 * and to `Info` (5 to 11). When the CPU time class exists, the extra arguments are appended as zero values,
 * so the same tests run on PHPUnit 11, 12 and 13.
 */
#[Package('framework')]
final class TelemetryInfoFactory
{
    /**
     * Named by string so this file never references a class that PHPUnit 11 and 12 do not ship.
     */
    private const CPU_TIME_CLASS = 'PHPUnit\Event\Telemetry\CpuTime';

    public static function create(): Info
    {
        $duration = Duration::fromSecondsAndNanoseconds(0, 0);
        $memory = MemoryUsage::fromBytes(0);

        $snapshotArguments = [
            HRTime::fromSecondsAndNanoseconds(0, 0),
            $memory,
            $memory,
            new GarbageCollectorStatus(0, 0, 0, 0, 0.0, 0.0, 0.0, 0.0, false, false, false, 0),
        ];
        $infoCpuTimes = [];

        if (class_exists(self::CPU_TIME_CLASS)) {
            $cpuTime = [self::CPU_TIME_CLASS, 'fromSecondsAndNanoseconds'];
            \assert(\is_callable($cpuTime));
            $zeroCpuTime = $cpuTime(0, 0);

            // user, system and total CPU time
            array_push($snapshotArguments, $zeroCpuTime, $zeroCpuTime, $zeroCpuTime);
            // the same three, each since start and since the previous event
            $infoCpuTimes = array_fill(0, 6, $zeroCpuTime);
        }

        return new Info(new Snapshot(...$snapshotArguments), $duration, $memory, $duration, $memory, ...$infoCpuTimes);
    }
}
