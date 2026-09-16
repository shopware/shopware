<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Flow\Dispatching;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\Flow\Dispatching\AbstractFlowLoader;
use Shopware\Core\Content\Flow\Dispatching\BufferedFlow;
use Shopware\Core\Content\Flow\Dispatching\BufferedFlowExecutor;
use Shopware\Core\Content\Flow\Dispatching\BufferedFlowQueue;
use Shopware\Core\Content\Flow\Dispatching\FlowExecutor;
use Shopware\Core\Content\Flow\Dispatching\FlowFactory;
use Shopware\Core\Content\Flow\Dispatching\StorableFlow;
use Shopware\Core\Content\Flow\Dispatching\Struct\Flow;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Generator;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(BufferedFlowExecutor::class)]
class BufferedFlowExecutorTest extends TestCase
{
    private Stub&BufferedFlowQueue $bufferedFlowQueueMock;

    private Stub&AbstractFlowLoader $flowLoaderMock;

    private Stub&FlowFactory $flowFactoryMock;

    private Stub&FlowExecutor $flowExecutorMock;

    private Stub&LoggerInterface $loggerMock;

    protected function setUp(): void
    {
        $this->bufferedFlowQueueMock = static::createStub(BufferedFlowQueue::class);
        $this->flowLoaderMock = static::createStub(AbstractFlowLoader::class);
        $this->flowFactoryMock = static::createStub(FlowFactory::class);
        $this->flowExecutorMock = static::createStub(FlowExecutor::class);
        $this->loggerMock = static::createStub(LoggerInterface::class);
    }

    public function testExecutesBufferedFlows(): void
    {
        $bufferedFlow = $this->createBufferedFlow(new OrderEntity());

        $bufferedFlowQueue = $this->createMock(BufferedFlowQueue::class);
        $bufferedFlowQueue->expects($this->exactly(2))
            ->method('isEmpty')
            ->willReturnOnConsecutiveCalls(false, true);

        $bufferedFlowQueue->expects($this->once())
            ->method('dequeueFlows')
            ->willReturn([$bufferedFlow]);

        $flowPayload = new Flow(Uuid::randomHex());
        $flowLoader = $this->createMock(AbstractFlowLoader::class);
        $flowLoader->expects($this->once())
            ->method('load')
            ->willReturn([
                'checkout.order.placed' => [
                    [
                        'id' => 'flow-1',
                        'name' => 'Order placed',
                        'payload' => $flowPayload,
                    ],
                ],
            ]);

        $flow = new StorableFlow($bufferedFlow->eventName, $bufferedFlow->eventContext, [], []);
        $flowFactory = $this->createMock(FlowFactory::class);
        $flowFactory->expects($this->once())
            ->method('restoreBuffered')
            ->with($bufferedFlow)
            ->willReturn($flow);

        $flowExecutor = $this->createMock(FlowExecutor::class);
        $flowExecutor->expects($this->once())
            ->method('executeFlows')
            ->with(
                [
                    [
                        'id' => 'flow-1',
                        'name' => 'Order placed',
                        'payload' => $flowPayload,
                    ],
                ],
                $flow,
            );

        $this->buildExecutor($bufferedFlowQueue, $flowLoader, $flowFactory, $flowExecutor)
            ->executeBufferedFlows();
    }

    public function testExecuteBufferedEventsWithoutFlows(): void
    {
        $bufferedFlow = $this->createBufferedFlow(new OrderEntity());
        $this->bufferedFlowQueueMock->method('isEmpty')->willReturnOnConsecutiveCalls(false, true);
        $this->bufferedFlowQueueMock->method('dequeueFlows')->willReturn([$bufferedFlow]);

        $flow = new StorableFlow($bufferedFlow->eventName, $bufferedFlow->eventContext, [], []);
        $flowFactory = $this->createMock(FlowFactory::class);
        $flowFactory->expects($this->once())->method('restoreBuffered')->with($bufferedFlow)->willReturn($flow);

        $flowLoader = $this->createMock(AbstractFlowLoader::class);
        $flowLoader->expects($this->once())->method('load')->willReturn([]);

        $this->buildExecutor(flowLoader: $flowLoader, flowFactory: $flowFactory)
            ->executeBufferedFlows();
    }

    public function testLogsErrorIfMaximumExecutionDepthIsExceeded(): void
    {
        $bufferedFlow = $this->createBufferedFlow(new OrderEntity());
        $this->bufferedFlowQueueMock->method('isEmpty')->willReturn(false);
        $this->bufferedFlowQueueMock->method('dequeueFlows')->willReturn([$bufferedFlow]);
        $this->flowLoaderMock->method('load')->willReturn([]);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('error')
            ->with(
                'Maximum execution depth reached for buffered flow executor. This might be caused by a cyclic flow execution.',
                ['bufferedEvents' => ['checkout.order.placed']],
            );

        $this->buildExecutor(logger: $logger)
            ->executeBufferedFlows();
    }

    private function buildExecutor(
        ?BufferedFlowQueue $bufferedFlowQueue = null,
        ?AbstractFlowLoader $flowLoader = null,
        ?FlowFactory $flowFactory = null,
        ?FlowExecutor $flowExecutor = null,
        ?LoggerInterface $logger = null,
    ): BufferedFlowExecutor {
        return new BufferedFlowExecutor(
            $bufferedFlowQueue ?? $this->bufferedFlowQueueMock,
            $flowLoader ?? $this->flowLoaderMock,
            $flowFactory ?? $this->flowFactoryMock,
            $flowExecutor ?? $this->flowExecutorMock,
            $logger ?? $this->loggerMock,
        );
    }

    private function createBufferedFlow(OrderEntity $order): BufferedFlow
    {
        $context = Generator::generateSalesChannelContext();

        $event = new CheckoutOrderPlacedEvent($context, $order);

        return new BufferedFlow($event->getName(), $event->getContext(), []);
    }
}
