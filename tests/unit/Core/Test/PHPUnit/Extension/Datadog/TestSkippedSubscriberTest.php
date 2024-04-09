<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Test\PHPUnit\Extension\Datadog;

use PHPUnit\Event\Code\Phpt;
use PHPUnit\Event\Telemetry\Duration;
use PHPUnit\Event\Telemetry\GarbageCollectorStatus;
use PHPUnit\Event\Telemetry\HRTime;
use PHPUnit\Event\Telemetry\Info;
use PHPUnit\Event\Telemetry\MemoryUsage;
use PHPUnit\Event\Telemetry\Snapshot;
use PHPUnit\Event\Test\Skipped;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Test\PHPUnit\Extension\Common\TimeKeeper;
use Shopware\Core\Test\PHPUnit\Extension\Datadog\DatadogPayload;
use Shopware\Core\Test\PHPUnit\Extension\Datadog\DatadogPayloadCollection;
use Shopware\Core\Test\PHPUnit\Extension\Datadog\Subscriber\TestSkippedSubscriber;

/**
 * @internal
 */
#[CoversClass(TestSkippedSubscriber::class)]
class TestSkippedSubscriberTest extends TestCase
{
    public function testNotifyWithFeatureFlagSkippedEvent(): void
    {
        $skipped = new DatadogPayloadCollection();

        $event = $this->buildEvent('Skipping feature test for flag');
        $subscriber = new TestSkippedSubscriber(new TimeKeeper(), $skipped);

        $subscriber->notify($event);

        static::assertEmpty($skipped);
    }

    public function testNotifyWithLegitSkippedEvent(): void
    {
        $skipped = new DatadogPayloadCollection();

        $expected = new DatadogPayload(
            'phpunit',
            'phpunit,test:skipped',
            'Test Skipped (fakeFile)',
            'PHPUnit',
            'fakeFile',
            0.0
        );

        $event = $this->buildEvent('');
        $subscriber = new TestSkippedSubscriber(new TimeKeeper(), $skipped);

        $subscriber->notify($event);

        static::assertEquals($expected, $skipped->first());
    }

    private function buildEvent(string $message): Skipped
    {
        $time = HRTime::fromSecondsAndNanoseconds(0, 0);
        $duration = Duration::fromSecondsAndNanoseconds(0, 0);
        $memory = MemoryUsage::fromBytes(0);
        $gc = new GarbageCollectorStatus(
            0,
            0,
            0,
            0,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
        );
        $snap = new Snapshot($time, $memory, $memory, $gc);

        return new Skipped(new Info($snap, $duration, $memory, $duration, $memory), new Phpt('fakeFile'), $message);
    }
}
