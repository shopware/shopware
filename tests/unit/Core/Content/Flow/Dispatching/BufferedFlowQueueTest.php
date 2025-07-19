<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Flow\Dispatching;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Flow\Dispatching\BufferedFlowQueue;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Stub\Flow\DummyEvent;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(BufferedFlowQueue::class)]
class BufferedFlowQueueTest extends TestCase
{
    private BufferedFlowQueue $bufferedFlowQueue;

    protected function setUp(): void
    {
        $this->bufferedFlowQueue = new BufferedFlowQueue();
    }

    public function testCanDetermineIfQueueIsEmpty(): void
    {
        static::assertTrue($this->bufferedFlowQueue->isEmpty());

        $this->bufferedFlowQueue->queueFlow(new DummyEvent());

        static::assertFalse($this->bufferedFlowQueue->isEmpty());
    }

    public function testCanDequeueFlows(): void
    {
        $event = new DummyEvent();
        $this->bufferedFlowQueue->queueFlow($event);

        $flows = $this->bufferedFlowQueue->dequeueFlows();

        static::assertCount(1, $flows);
        static::assertSame($event, $flows[0]);
        static::assertTrue($this->bufferedFlowQueue->isEmpty());
    }
}
