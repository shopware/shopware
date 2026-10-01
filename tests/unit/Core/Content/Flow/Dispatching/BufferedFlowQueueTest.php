<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Flow\Dispatching;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Flow\Dispatching\BufferedFlow;
use Shopware\Core\Content\Flow\Dispatching\BufferedFlowQueue;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;

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

        $this->bufferedFlowQueue->queueFlow(new BufferedFlow('dummy_event', Context::createDefaultContext(), []));

        static::assertFalse($this->bufferedFlowQueue->isEmpty());
    }

    public function testCanDequeueFlows(): void
    {
        $bufferedFlow = new BufferedFlow('dummy_event', Context::createDefaultContext(), []);
        $this->bufferedFlowQueue->queueFlow($bufferedFlow);

        $flows = $this->bufferedFlowQueue->dequeueFlows();

        static::assertCount(1, $flows);
        static::assertSame($bufferedFlow, $flows[0]);
        static::assertTrue($this->bufferedFlowQueue->isEmpty());
    }
}
