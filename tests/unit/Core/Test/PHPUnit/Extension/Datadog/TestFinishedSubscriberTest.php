<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Test\PHPUnit\Extension\Datadog;

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
use Shopware\Core\Test\PHPUnit\Extension\Common\TimeKeeper;
use Shopware\Core\Test\PHPUnit\Extension\Datadog\DatadogPayload;
use Shopware\Core\Test\PHPUnit\Extension\Datadog\DatadogPayloadCollection;
use Shopware\Core\Test\PHPUnit\Extension\Datadog\Subscriber\TestFinishedSubscriber;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(TestFinishedSubscriber::class)]
class TestFinishedSubscriberTest extends TestCase
{
    public function testATestBelowTheThresholdIsNotRecorded(): void
    {
        $slowTests = new DatadogPayloadCollection();
        $timeKeeper = new TimeKeeper();
        $timeKeeper->start('fakeFile', HRTime::fromSecondsAndNanoseconds(0, 0));

        $subscriber = new TestFinishedSubscriber($timeKeeper, $slowTests);
        $subscriber->notify($this->buildEvent(1));

        static::assertCount(0, $slowTests);
    }

    public function testATestAboveTheThresholdIsRecordedAsSlow(): void
    {
        $expected = new DatadogPayload(
            'phpunit',
            'phpunit,test:slow',
            'Slow test: Test Finished (fakeFile)',
            'PHPUnit',
            'fakeFile',
            3.0,
        );

        $slowTests = new DatadogPayloadCollection();
        $timeKeeper = new TimeKeeper();
        $timeKeeper->start('fakeFile', HRTime::fromSecondsAndNanoseconds(0, 0));

        $subscriber = new TestFinishedSubscriber($timeKeeper, $slowTests);
        $subscriber->notify($this->buildEvent(3));

        static::assertEquals($expected, $slowTests->first());
    }

    private function buildEvent(int $seconds): Finished
    {
        $time = HRTime::fromSecondsAndNanoseconds($seconds, 0);
        $duration = Duration::fromSecondsAndNanoseconds(0, 0);
        $memory = MemoryUsage::fromBytes(0);
        $gc = new GarbageCollectorStatus(0, 0, 0, 0, 0.0, 0.0, 0.0, 0.0, false, false, false, 0);
        $snap = new Snapshot($time, $memory, $memory, $gc);

        return new Finished(
            new Info($snap, $duration, $memory, $duration, $memory),
            new Phpt('fakeFile'),
            0
        );
    }
}
