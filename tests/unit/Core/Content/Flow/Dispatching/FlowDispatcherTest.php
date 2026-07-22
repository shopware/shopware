<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Flow\Dispatching;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\PDO\Exception as DbalPdoException;
use Doctrine\DBAL\Exception\TableNotFoundException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\Flow\Dispatching\BufferedFlow;
use Shopware\Core\Content\Flow\Dispatching\BufferedFlowQueue;
use Shopware\Core\Content\Flow\Dispatching\FlowDispatcher;
use Shopware\Core\Content\Flow\Dispatching\FlowExecutor;
use Shopware\Core\Content\Flow\Dispatching\FlowFactory;
use Shopware\Core\Content\Flow\Dispatching\FlowLoader;
use Shopware\Core\Content\Flow\Dispatching\StorableFlow;
use Shopware\Core\Content\Flow\Dispatching\Struct\Flow;
use Shopware\Core\Content\Flow\Exception\ExecuteSequenceException;
use Shopware\Core\Content\Flow\FlowException;
use Shopware\Core\Framework\Event\FlowLogEvent;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Generator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(FlowDispatcher::class)]
class FlowDispatcherTest extends TestCase
{
    private ContainerInterface $container;

    private Stub&EventDispatcherInterface $dispatcher;

    private Stub&FlowFactory $flowFactory;

    private Stub&Connection $connection;

    private Stub&LoggerInterface $logger;

    private Stub&BufferedFlowQueue $bufferedFlowQueue;

    private FlowDispatcher $flowDispatcher;

    protected function setUp(): void
    {
        $this->container = new ContainerBuilder();
        $this->dispatcher = static::createStub(EventDispatcherInterface::class);
        $this->logger = static::createStub(LoggerInterface::class);
        $this->flowFactory = static::createStub(FlowFactory::class);
        $this->connection = static::createStub(Connection::class);
        $this->bufferedFlowQueue = static::createStub(BufferedFlowQueue::class);

        $this->container->set('logger', $this->logger);
        $this->container->set(FlowFactory::class, $this->flowFactory);
        $this->container->set(Connection::class, $this->connection);
        $this->container->set(BufferedFlowQueue::class, $this->bufferedFlowQueue);

        $this->flowDispatcher = new FlowDispatcher($this->dispatcher, $this->container);
    }

    public function testDispatchWithNotFlowEventAware(): void
    {
        $event = $this->createCheckoutOrderPlacedEvent(new OrderEntity());

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->expects($this->once())->method('dispatch');
        $flowDispatcher = new FlowDispatcher($dispatcher, $this->container);

        $flowDispatcher->dispatch($event);
    }

    public function testDispatchSkipTrigger(): void
    {
        $event = $this->createCheckoutOrderPlacedEvent(new OrderEntity());

        $context = $event->getContext();
        $context->addState('skipTriggerFlow');

        $flowLogEvent = new FlowLogEvent(FlowLogEvent::NAME, $event);
        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->expects($this->exactly(2))
            ->method('dispatch')
            ->willReturnOnConsecutiveCalls($event, $flowLogEvent);
        $flowDispatcher = new FlowDispatcher($dispatcher, $this->container);

        $flowDispatcher->dispatch($event);
    }

    public function testDispatchWithoutFlows(): void
    {
        $event = $this->createCheckoutOrderPlacedEvent(new OrderEntity());

        $flowLogEvent = new FlowLogEvent(FlowLogEvent::NAME, $event);
        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->expects($this->exactly(2))
            ->method('dispatch')
            ->willReturnOnConsecutiveCalls($event, $flowLogEvent);
        $flowDispatcher = new FlowDispatcher($dispatcher, $this->container);

        $flowFactory = $this->createMock(FlowFactory::class);
        $this->container->set(FlowFactory::class, $flowFactory);

        if (Feature::isActive('FLOW_EXECUTION_AFTER_BUSINESS_PROCESS') || Feature::isActive('v6.8.0.0')) {
            $bufferedFlow = new BufferedFlow($event->getName(), $event->getContext(), []);
            $flowFactory->expects($this->once())
                ->method('createBuffered')
                ->with($event)
                ->willReturn($bufferedFlow);
            $bufferedFlowQueue = $this->createMock(BufferedFlowQueue::class);
            $bufferedFlowQueue->expects($this->once())
                ->method('queueFlow')
                ->with($bufferedFlow);
            $this->container->set(BufferedFlowQueue::class, $bufferedFlowQueue);
        } else {
            $flow = new StorableFlow('state_enter.order.state.in_progress', $event->getContext(), [], []);
            $flowFactory->expects($this->once())
                ->method('create')
                ->willReturn($flow);

            $flowLoader = $this->createMock(FlowLoader::class);
            $this->container->set(FlowLoader::class, $flowLoader);
            $flowLoader->expects($this->once())
                ->method('load')
                ->willReturn([]);
        }

        $flowDispatcher->dispatch($event);
    }

    /**
     * @param array<string, mixed> $flows
     */
    #[DataProvider('flowsData')]
    public function testDispatch(array $flows): void
    {
        $event = $this->createCheckoutOrderPlacedEvent(new OrderEntity());

        $flowLogEvent = new FlowLogEvent(FlowLogEvent::NAME, $event);

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->expects($this->exactly(2))
            ->method('dispatch')
            ->willReturnOnConsecutiveCalls($event, $flowLogEvent);
        $flowDispatcher = new FlowDispatcher($dispatcher, $this->container);

        if (Feature::isActive('FLOW_EXECUTION_AFTER_BUSINESS_PROCESS') || Feature::isActive('v6.8.0.0')) {
            $bufferedFlow = new BufferedFlow($event->getName(), $event->getContext(), []);
            $flowFactory = $this->createMock(FlowFactory::class);
            $flowFactory->expects($this->once())
                ->method('createBuffered')
                ->with($event)
                ->willReturn($bufferedFlow);
            $this->container->set(FlowFactory::class, $flowFactory);
            $bufferedFlowQueue = $this->createMock(BufferedFlowQueue::class);
            $bufferedFlowQueue->expects($this->once())
                ->method('queueFlow')
                ->with($bufferedFlow);
            $this->container->set(BufferedFlowQueue::class, $bufferedFlowQueue);
        }

        $flowDispatcher->dispatch($event);
    }

    public function testNestedTransactionExceptionsAreRethrownWhenSavePointsAreNotEnabled(): void
    {
        Feature::skipTestIfActive('FLOW_EXECUTION_AFTER_BUSINESS_PROCESS', $this);
        Feature::skipTestIfActive('v6.8.0.0', $this);
        $event = $this->createCheckoutOrderPlacedEvent(new OrderEntity());

        $flowLogEvent = new FlowLogEvent(FlowLogEvent::NAME, $event);
        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->expects($this->exactly(2))
            ->method('dispatch')
            ->willReturnOnConsecutiveCalls($event, $flowLogEvent);
        $flowDispatcher = new FlowDispatcher($dispatcher, $this->container);

        $flow = new StorableFlow('state_enter.order.state.in_progress', $event->getContext(), [], []);
        $flowFactory = $this->createMock(FlowFactory::class);
        $flowFactory->expects($this->once())
            ->method('create')
            ->willReturn($flow);
        $this->container->set(FlowFactory::class, $flowFactory);

        $flowLoader = static::createStub(FlowLoader::class);
        $flowLoader->method('load')->willReturn([
            'state_enter.order.state.in_progress' => [
                [
                    'id' => 'flow-1',
                    'name' => 'Order enters status in progress',
                    'payload' => new Flow(Uuid::randomHex()),
                ],
            ],
        ]);

        $internalException = FlowException::transactionFailed(new TableNotFoundException(
            new DbalPdoException('Table not found', null, 1146),
            null
        ));

        $flowExecutor = $this->createMock(FlowExecutor::class);
        $flowExecutor->expects($this->once())
            ->method('execute')
            ->willThrowException(new ExecuteSequenceException(
                'flow-1',
                'sequence-1',
                $internalException->getMessage(),
                0,
                $internalException
            ));

        $this->container->set(FlowLoader::class, $flowLoader);
        $this->container->set(FlowExecutor::class, $flowExecutor);

        $this->expectExceptionObject($internalException);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('warning')
            ->with(
                "Could not execute flow with error message:\nFlow name: Order enters status in progress\nFlow id: flow-1\nSequence id: sequence-1\nFlow action transaction could not be committed and was rolled back. Exception: An exception occurred in the driver: Table not found\nError Code: 0\n",
                static::callback(static function (array $context) {
                    return $context['exception'] instanceof ExecuteSequenceException;
                })
            );
        $this->container->set('logger', $logger);

        $flowDispatcher->dispatch($event);
    }

    public function testExceptionsAreLoggedAndExecutionContinuesWhenNestedTransactionsWithSavePointsIsEnabled(): void
    {
        Feature::skipTestIfActive('FLOW_EXECUTION_AFTER_BUSINESS_PROCESS', $this);
        Feature::skipTestIfActive('v6.8.0.0', $this);
        $event = $this->createCheckoutOrderPlacedEvent(new OrderEntity());

        $this->dispatcher->method('dispatch')->willReturnOnConsecutiveCalls(
            $event,
            new FlowLogEvent(FlowLogEvent::NAME, $event),
        );

        $flow = new StorableFlow('state_enter.order.state.in_progress', $event->getContext(), [], []);
        $this->flowFactory->method('create')->willReturn($flow);

        $flowLoader = static::createStub(FlowLoader::class);
        $flowLoader->method('load')->willReturn([
            'state_enter.order.state.in_progress' => [
                [
                    'id' => 'flow-1',
                    'name' => 'Order enters status in progress',
                    'payload' => new Flow(Uuid::randomHex()),
                ],
            ],
        ]);

        $internalException = FlowException::transactionFailed(new TableNotFoundException(
            new DbalPdoException('Table not found', null, 1146),
            null
        ));

        $flowExecutor = $this->createMock(FlowExecutor::class);
        $flowExecutor->expects($this->once())
            ->method('execute')
            ->willThrowException(new ExecuteSequenceException(
                'flow-1',
                'sequence-1',
                $internalException->getMessage(),
                0,
                $internalException
            ));

        $this->container->set(FlowLoader::class, $flowLoader);
        $this->container->set(FlowExecutor::class, $flowExecutor);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('warning')
            ->with(
                "Could not execute flow with error message:\nFlow name: Order enters status in progress\nFlow id: flow-1\nSequence id: sequence-1\nFlow action transaction could not be committed and was rolled back. Exception: An exception occurred in the driver: Table not found\nError Code: 0\n",
                static::callback(static function (array $context) {
                    return $context['exception'] instanceof ExecuteSequenceException;
                })
            );
        $this->container->set('logger', $logger);

        $this->flowDispatcher->dispatch($event);
    }

    public static function flowsData(): \Generator
    {
        yield 'Single flow' => [[
            'state_enter.order.state.in_progress' => [
                [
                    'id' => Uuid::randomHex(),
                    'name' => 'Order enters status in progress',
                    'payload' => new Flow(Uuid::randomHex()),
                ],
            ],
        ]];

        yield 'Multi flows' => [[
            'state_enter.order.state.in_progress' => [
                [
                    'id' => Uuid::randomHex(),
                    'name' => 'Order enters status in progress',
                    'payload' => new Flow(Uuid::randomHex()),
                ],
                [
                    'id' => Uuid::randomHex(),
                    'name' => 'Some flows',
                    'payload' => new Flow(Uuid::randomHex()),
                ],
            ],
        ]];
    }

    private function createCheckoutOrderPlacedEvent(OrderEntity $order): CheckoutOrderPlacedEvent
    {
        $context = Generator::generateSalesChannelContext();

        return new CheckoutOrderPlacedEvent($context, $order);
    }
}
