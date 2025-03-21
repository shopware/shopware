<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Flow\Dispatching;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
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

    private MockObject&ContainerInterface $containerMock;

    private MockObject&BufferedFlowExecutor $bufferedFlowExecutorMock;

    private MockObject&BufferedFlowQueue $bufferedFlowQueueMock;

    protected function setUp(): void
    {
        $this->containerMock = $this->createMock(ContainerInterface::class);
        $this->bufferedFlowQueueMock = $this->createMock(BufferedFlowQueue::class);
        $this->bufferedFlowExecutorMock = $this->createMock(BufferedFlowExecutor::class);

        $this->bufferedFlowExecutionTriggersListener = new BufferedFlowExecutionTriggersListener(
            $this->containerMock,
            $this->bufferedFlowQueueMock,
        );
    }

    public function testRegistersBufferedFlowExecutionTriggers(): void
    {
        if (Feature::isActive('FLOW_EXECUTION_AFTER_BUSINESS_PROCESS')) {
            static::assertEquals(
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
        $this->bufferedFlowQueueMock->expects($this->once())
            ->method('isEmpty')
            ->willReturn(true);

        $this->containerMock->expects($this->never())
            ->method('get');

        $this->bufferedFlowExecutionTriggersListener->triggerBufferedFlowExecution();
    }

    public function testExecutesBufferedFlowsIfFlowsAreQueued(): void
    {
        $this->bufferedFlowQueueMock->method('isEmpty')
            ->willReturn(false);

        $this->containerMock->expects($this->once())
            ->method('get')
            ->with(BufferedFlowExecutor::class)
            ->willReturn($this->bufferedFlowExecutorMock);

        $this->bufferedFlowExecutorMock->expects($this->once())
            ->method('executeBufferedFlows');

        $this->bufferedFlowExecutionTriggersListener->triggerBufferedFlowExecution();
    }
}
