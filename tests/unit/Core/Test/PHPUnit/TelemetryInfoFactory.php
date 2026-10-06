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
 * and to `Info` (5 to 11). Both constructors are filled up to their declared parameter count, each missing
 * parameter with a zero value of its own type, so the same tests run on PHPUnit 11, 12 and 13.
 */
#[Package('framework')]
final class TelemetryInfoFactory
{
    public static function create(): Info
    {
        $duration = Duration::fromSecondsAndNanoseconds(0, 0);
        $memory = MemoryUsage::fromBytes(0);

        $snapshot = self::construct(Snapshot::class, [
            HRTime::fromSecondsAndNanoseconds(0, 0),
            $memory,
            $memory,
            new GarbageCollectorStatus(0, 0, 0, 0, 0.0, 0.0, 0.0, 0.0, false, false, false, 0),
        ]);

        return self::construct(Info::class, [$snapshot, $duration, $memory, $duration, $memory]);
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     * @param list<object> $arguments the leading arguments every supported PHPUnit version declares
     *
     * @return T
     */
    private static function construct(string $class, array $arguments): object
    {
        $reflection = new \ReflectionClass($class);
        $constructor = $reflection->getConstructor();
        \assert($constructor !== null);

        foreach (\array_slice($constructor->getParameters(), \count($arguments)) as $parameter) {
            $arguments[] = self::zeroValueOf($parameter);
        }

        return $reflection->newInstanceArgs($arguments);
    }

    /**
     * The telemetry value classes PHPUnit added later (`CpuTime`) share the `fromSecondsAndNanoseconds()`
     * named constructor with `HRTime` and `Duration`; the class is taken from the parameter type so this
     * file never names a class that older PHPUnit versions do not ship.
     */
    private static function zeroValueOf(\ReflectionParameter $parameter): object
    {
        $type = $parameter->getType();
        \assert($type instanceof \ReflectionNamedType);

        $factory = [$type->getName(), 'fromSecondsAndNanoseconds'];
        \assert(\is_callable($factory));

        $value = $factory(0, 0);
        \assert(\is_object($value));

        return $value;
    }
}
