<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Test\PHPUnit\Extension\InAppPurchase\Subscriber;

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
use Shopware\Core\Framework\Store\InAppPurchase;
use Shopware\Core\Test\PHPUnit\Extension\InAppPurchase\Subscriber\TestFinishedSubscriber;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(TestFinishedSubscriber::class)]
class TestFinishedSubscriberTest extends TestCase
{
    public function testSubscriber(): void
    {
        InAppPurchase::registerPurchases([
            'test' => 'test',
        ]);

        $subscriber = new TestFinishedSubscriber();
        $subscriber->notify($this->buildEvent());

        static::assertEmpty(InAppPurchase::all());
    }

    private function buildEvent(): Finished
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

        return new Finished(
            new Info($snap, $duration, $memory, $duration, $memory),
            $this->valueObjectForEvents(),
            $this->numberOfAssertionsPerformed()
        );
    }
}
