<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Webhook\Health;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Framework\Adapter\Storage\AbstractKeyValueStorage;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Webhook\Health\EndpointLifecycle;
use Shopware\Core\Framework\Webhook\Health\WebhookHealthTick;
use Symfony\Component\Clock\MockClock;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(WebhookHealthTick::class)]
class WebhookHealthTickTest extends TestCase
{
    public function testDoesNothingWithoutALifecycleImplementation(): void
    {
        // PR-1 inertness: until an EndpointLifecycle is bound, a poll does no work at all.
        $storage = $this->createMock(AbstractKeyValueStorage::class);
        $storage->expects($this->never())->method('set');

        $tick = new WebhookHealthTick($storage, new MockClock(), new NullLogger());

        $tick->run();
    }

    public function testRunsTheDutiesAndWritesTheHeartbeat(): void
    {
        $clock = new MockClock('2026-07-02 12:00:00');

        $storage = $this->createMock(AbstractKeyValueStorage::class);
        $storage->expects($this->once())
            ->method('set')
            ->with(WebhookHealthTick::HEARTBEAT_STORAGE_KEY, '2026-07-02 12:00:00.000');

        $lifecycle = $this->createMock(EndpointLifecycle::class);
        $lifecycle->expects($this->once())->method('tick')->willReturn(3);

        $tick = new WebhookHealthTick($storage, $clock, new NullLogger(), $lifecycle);

        $tick->run();
    }

    public function testPollsInsideTheIntervalAreDebounced(): void
    {
        $clock = new MockClock('2026-07-02 12:00:00');

        $storage = $this->createMock(AbstractKeyValueStorage::class);
        $storage->expects($this->exactly(2))->method('set');

        $lifecycle = $this->createMock(EndpointLifecycle::class);
        $lifecycle->expects($this->exactly(2))->method('tick')->willReturn(0);

        $tick = new WebhookHealthTick($storage, $clock, new NullLogger(), $lifecycle);

        // First poll ticks; the next polls within the interval do nothing.
        $tick->run();
        $clock->modify('+1 second');
        $tick->run();
        $clock->modify('+30 seconds');
        $tick->run();

        // Past the interval the next poll ticks again.
        $clock->modify('+30 seconds');
        $tick->run();
    }

    public function testAFailingTickNeverThrowsIntoTheWorkerAndSkipsTheHeartbeat(): void
    {
        // The missing heartbeat write is what makes the failure observable — as a stale
        // heartbeat on the health-status endpoint, not as a dead delivery worker.
        $storage = $this->createMock(AbstractKeyValueStorage::class);
        $storage->expects($this->never())->method('set');

        $lifecycle = $this->createMock(EndpointLifecycle::class);
        $lifecycle->expects($this->once())->method('tick')->willThrowException(new \RuntimeException('duty blew up'));

        $tick = new WebhookHealthTick($storage, new MockClock(), new NullLogger(), $lifecycle);

        $tick->run();
    }

    public function testAFailedTickIsNotRetriedBeforeTheNextInterval(): void
    {
        // The debounce advances even on failure: a broken duty must not turn every
        // one-second worker poll into a failing scan.
        $clock = new MockClock('2026-07-02 12:00:00');

        $lifecycle = $this->createMock(EndpointLifecycle::class);
        $lifecycle->expects($this->exactly(2))->method('tick')->willThrowException(new \RuntimeException('duty blew up'));

        $tick = new WebhookHealthTick(
            $this->createMock(AbstractKeyValueStorage::class),
            $clock,
            new NullLogger(),
            $lifecycle,
        );

        $tick->run();
        $clock->modify('+1 second');
        $tick->run();

        $clock->modify('+60 seconds');
        $tick->run();
    }
}
