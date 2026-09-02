<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Test\PHPUnit\Extension\DatabaseDiff;

use PHPUnit\Event\Code\Phpt;
use PHPUnit\Event\Telemetry\Duration;
use PHPUnit\Event\Telemetry\GarbageCollectorStatus;
use PHPUnit\Event\Telemetry\HRTime;
use PHPUnit\Event\Telemetry\Info;
use PHPUnit\Event\Telemetry\MemoryUsage;
use PHPUnit\Event\Telemetry\Snapshot;
use PHPUnit\Event\Test\Finished;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\PHPUnit\Extension\DatabaseDiff\DbState;
use Shopware\Core\Test\PHPUnit\Extension\DatabaseDiff\Subscriber\TestFinishedSubscriber;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(TestFinishedSubscriber::class)]
class TestFinishedSubscriberTest extends TestCase
{
    public function testACleanDbStatePrintsNothing(): void
    {
        $dbState = static::createStub(DbState::class);
        $dbState->method('getDiff')->willReturn([]);

        $this->expectOutputString('');

        (new TestFinishedSubscriber($dbState))->notify($this->buildEvent());
    }

    public function testADirtyDbStatePrintsTheDiff(): void
    {
        $dbState = static::createStub(DbState::class);
        $dbState->method('getDiff')->willReturn(['product' => 3]);

        $this->expectOutputRegex('/product/');

        (new TestFinishedSubscriber($dbState))->notify($this->buildEvent());
    }

    private function buildEvent(): Finished
    {
        $time = HRTime::fromSecondsAndNanoseconds(0, 0);
        $duration = Duration::fromSecondsAndNanoseconds(0, 0);
        $memory = MemoryUsage::fromBytes(0);
        $gc = new GarbageCollectorStatus(0, 0, 0, 0, 0.0, 0.0, 0.0, 0.0, false, false, false, 0);
        $snap = new Snapshot($time, $memory, $memory, $gc);

        return new Finished(new Info($snap, $duration, $memory, $duration, $memory), new Phpt('fakeFile'), 0);
    }
}
