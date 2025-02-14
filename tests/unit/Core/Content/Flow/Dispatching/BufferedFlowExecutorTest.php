<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Flow\Dispatching;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\Flow\Dispatching\AbstractFlowLoader;
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
    private BufferedFlowExecutor $bufferedFlowExecutor;

    private MockObject&BufferedFlowQueue $bufferedFlowQueueMock;

    private MockObject&AbstractFlowLoader $flowLoaderMock;

    private MockObject&FlowFactory $flowFactoryMock;

    private MockObject&FlowExecutor $flowExecutorMock;

    private MockObject&LoggerInterface $loggerMock;

    protected function setUp(): void
    {
        $this->bufferedFlowQueueMock = $this->createMock(BufferedFlowQueue::class);
        $this->flowLoaderMock = $this->createMock(AbstractFlowLoader::class);
        $this->flowFactoryMock = $this->createMock(FlowFactory::class);
        $this->flowExecutorMock = $this->createMock(FlowExecutor::class);
        $this->loggerMock = $this->createMock(LoggerInterface::class);

        $this->bufferedFlowExecutor = new BufferedFlowExecutor(
            $this->bufferedFlowQueueMock,
            $this->flowLoaderMock,
            $this->flowFactoryMock,
            $this->flowExecutorMock,
            $this->loggerMock,
        );
    }

    public function testExecutesBufferedFlows(): void
    {
        $event = $this->createCheckoutOrderPlacedEvent(new OrderEntity());

        $this->bufferedFlowQueueMock->expects(static::exactly(2))
            ->method('isEmpty')
            ->willReturnOnConsecutiveCalls(false, true);

        $this->bufferedFlowQueueMock->expects(static::once())
            ->method('dequeueFlows')
            ->willReturn([$event]);

        $flowPayload = new Flow(Uuid::randomHex());
        $this->flowLoaderMock->expects(static::once())
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

        $flow = new StorableFlow('checkout.order.placed', $event->getContext(), [], []);
        $this->flowFactoryMock->expects(static::once())
            ->method('create')
            ->with($event)
            ->willReturn($flow);

        $this->flowExecutorMock->expects(static::once())
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

        $this->bufferedFlowExecutor->executeBufferedFlows();
    }

    public function testExecuteBufferedEventsWithoutFlows(): void
    {
        $event = $this->createCheckoutOrderPlacedEvent(new OrderEntity());
        $this->bufferedFlowQueueMock->method('isEmpty')->willReturnOnConsecutiveCalls(false, true);
        $this->bufferedFlowQueueMock->method('dequeueFlows')->willReturn([$event]);

        $flow = new StorableFlow('name', $event->getContext(), [], []);
        $this->flowFactoryMock->expects(static::once())->method('create')->willReturn($flow);

        $this->flowLoaderMock->expects(static::once())->method('load')->willReturn([]);

        $this->bufferedFlowExecutor->executeBufferedFlows();
    }

    public function testLogsErrorIfMaximumExecutionDepthIsExceeded(): void
    {
        $event = $this->createCheckoutOrderPlacedEvent(new OrderEntity());
        $this->bufferedFlowQueueMock->method('isEmpty')->willReturn(false);
        $this->bufferedFlowQueueMock->method('dequeueFlows')->willReturn([$event]);
        $this->flowLoaderMock->method('load')->willReturn([]);

        $this->loggerMock->expects(static::once())
            ->method('error')
            ->with(
                'Maximum execution depth reached for buffered flow executor. This might be caused by a cyclic flow execution.',
                ['bufferedEvents' => ['checkout.order.placed']],
            );

        $this->bufferedFlowExecutor->executeBufferedFlows();
    }

    private function createCheckoutOrderPlacedEvent(OrderEntity $order): CheckoutOrderPlacedEvent
    {
        $context = Generator::generateSalesChannelContext();

        return new CheckoutOrderPlacedEvent($context, $order);
    }
}
