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
use PHPUnit\Event\Test\Failed;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\PHPUnit\Extension\Common\TimeKeeper;
use Shopware\Core\Test\PHPUnit\Extension\Datadog\DatadogPayload;
use Shopware\Core\Test\PHPUnit\Extension\Datadog\DatadogPayloadCollection;
use Shopware\Core\Test\PHPUnit\Extension\Datadog\Subscriber\TestFailedSubscriber;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(TestFailedSubscriber::class)]
class TestFailedSubscriberTest extends TestCase
{
    public function testNotifyRecordsTheFailure(): void
    {
        $expected = new DatadogPayload(
            'phpunit',
            'phpunit,test:failed',
            'Test Failed (fakeFile)' . \PHP_EOL . 'blabla',
            'PHPUnit',
            'fakeFile',
            0.0,
        );

        $failed = new DatadogPayloadCollection();
        $subscriber = new TestFailedSubscriber(new TimeKeeper(), $failed);

        $subscriber->notify($this->buildEvent());

        static::assertEquals($expected, $failed->first());
    }

    private function buildEvent(): Failed
    {
        $time = HRTime::fromSecondsAndNanoseconds(0, 0);
        $duration = Duration::fromSecondsAndNanoseconds(0, 0);
        $memory = MemoryUsage::fromBytes(0);
        $gc = new GarbageCollectorStatus(0, 0, 0, 0, 0.0, 0.0, 0.0, 0.0, false, false, false, 0);
        $snap = new Snapshot($time, $memory, $memory, $gc);

        return new Failed(
            new Info($snap, $duration, $memory, $duration, $memory),
            new Phpt('fakeFile'),
            new Throwable(self::class, 'blabla', '', '', null),
            null
        );
    }
}
