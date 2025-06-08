<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Test\PHPUnit\Extension\Datadog;

use PHPUnit\Event\Code\Phpt;
use PHPUnit\Event\Code\Throwable;
use PHPUnit\Event\Telemetry\Duration;
use PHPUnit\Event\Telemetry\GarbageCollectorStatus;
use PHPUnit\Event\Telemetry\HRTime;
use PHPUnit\Event\Telemetry\Info;
use PHPUnit\Event\Telemetry\MemoryUsage;
use PHPUnit\Event\Telemetry\Snapshot;
use PHPUnit\Event\Test\Errored;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Test\PHPUnit\Extension\Common\TimeKeeper;
use Shopware\Core\Test\PHPUnit\Extension\Datadog\DatadogPayload;
use Shopware\Core\Test\PHPUnit\Extension\Datadog\DatadogPayloadCollection;
use Shopware\Core\Test\PHPUnit\Extension\Datadog\Subscriber\TestErroredSubscriber;

/**
 * @internal
 */
#[CoversClass(TestErroredSubscriber::class)]
class TestErroredSubscriberTest extends TestCase
{
    public function testNotifyWithErroredEvent(): void
    {
        $expected = new DatadogPayload(
            'phpunit',
            'phpunit,test:errored',
            'Test Errored (fakeFile)' . \PHP_EOL . 'blabla',
            'PHPUnit',
            'fakeFile',
            0.0,
        );

        $errored = new DatadogPayloadCollection();
        $event = $this->buildEvent(
            new Throwable(
                TestErroredSubscriberTest::class,
                'blabla',
                '',
                '',
                null
            )
        );

        $subscriber = new TestErroredSubscriber(new TimeKeeper(), $errored);

        $subscriber->notify($event);

        static::assertEquals($expected, $errored->first());
    }

    private function buildEvent(Throwable $throwable): Errored
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

        return new Errored(
            new Info($snap, $duration, $memory, $duration, $memory),
            new Phpt('fakeFile'),
            $throwable
        );
    }
}
