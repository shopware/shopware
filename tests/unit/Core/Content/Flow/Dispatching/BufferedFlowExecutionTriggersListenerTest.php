<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Flow\Dispatching;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Flow\Dispatching\BufferedFlowExecutionTriggersListener;
use Shopware\Core\Content\Flow\Dispatching\BufferedFlowExecutor;
use Shopware\Core\Content\Flow\Dispatching\BufferedFlowQueue;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(BufferedFlowExecutionTriggersListener::class)]
class BufferedFlowExecutionTriggersListenerTest extends TestCase
{
    private BufferedFlowExecutionTriggersListener $bufferedFlowExecutionTriggersListener;

    private ContainerInterface&Stub $containerMock;

    private BufferedFlowQueue&Stub $bufferedFlowQueueMock;

    protected function setUp(): void
    {
        $this->containerMock = static::createStub(ContainerInterface::class);
        $this->bufferedFlowQueueMock = static::createStub(BufferedFlowQueue::class);

        $this->bufferedFlowExecutionTriggersListener = $this->createListener();
    }

    public function testRegistersBufferedFlowExecutionTriggers(): void
    {
        if (Feature::isActive('FLOW_EXECUTION_AFTER_BUSINESS_PROCESS') || Feature::isActive('v6.8.0.0')) {
            static::assertSame(
                [
                    'kernel.terminate' => 'triggerBufferedFlowExecution',
                    'Symfony\Component\Messenger\Event\WorkerMessageHandledEvent' => 'triggerBufferedFlowExecution',
                    'console.terminate' => 'triggerBufferedFlowExecution',
                ],
                $this->bufferedFlowExecutionTriggersListener::getSubscribedEvents()
            );
        } else {
            static::assertEmpty($this->bufferedFlowExecutionTriggersListener::getSubscribedEvents());
        }
    }

    public function testDoesNotLoadServicesIfNoFlowsAreQueued(): void
    {
        $bufferedFlowQueue = $this->createMock(BufferedFlowQueue::class);
        $bufferedFlowQueue->expects($this->once())
            ->method('isEmpty')
            ->willReturn(true);

        $container = $this->createMock(ContainerInterface::class);
        $container->expects($this->never())
            ->method('get');

        $this->createListener($container, $bufferedFlowQueue)->triggerBufferedFlowExecution();
    }

    public function testExecutesBufferedFlowsIfFlowsAreQueued(): void
    {
        $bufferedFlowQueue = static::createStub(BufferedFlowQueue::class);
        $bufferedFlowQueue->method('isEmpty')
            ->willReturn(false);

        $bufferedFlowExecutor = $this->createMock(BufferedFlowExecutor::class);
        $bufferedFlowExecutor->expects($this->once())
            ->method('executeBufferedFlows');

        $container = $this->createMock(ContainerInterface::class);
        $container->expects($this->once())
            ->method('get')
            ->with(BufferedFlowExecutor::class)
            ->willReturn($bufferedFlowExecutor);

        $this->createListener($container, $bufferedFlowQueue)->triggerBufferedFlowExecution();
    }

    private function createListener(?ContainerInterface $container = null, ?BufferedFlowQueue $bufferedFlowQueue = null): BufferedFlowExecutionTriggersListener
    {
        return new BufferedFlowExecutionTriggersListener(
            $container ?? $this->containerMock,
            $bufferedFlowQueue ?? $this->bufferedFlowQueueMock,
        );
    }
}
