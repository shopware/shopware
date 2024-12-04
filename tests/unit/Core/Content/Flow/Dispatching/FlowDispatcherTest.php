<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Flow\Dispatching;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\PDO\Exception as DbalPdoException;
use Doctrine\DBAL\Exception\TableNotFoundException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\Flow\Dispatching\FlowDispatcher;
use Shopware\Core\Content\Flow\Dispatching\FlowExecutor;
use Shopware\Core\Content\Flow\Dispatching\FlowFactory;
use Shopware\Core\Content\Flow\Dispatching\FlowLoader;
use Shopware\Core\Content\Flow\Dispatching\StorableFlow;
use Shopware\Core\Content\Flow\Dispatching\Struct\Flow;
use Shopware\Core\Content\Flow\Exception\ExecuteSequenceException;
use Shopware\Core\Content\Flow\FlowException;
use Shopware\Core\Framework\Event\FlowLogEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Generator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('services-settings')]
#[CoversClass(FlowDispatcher::class)]
class FlowDispatcherTest extends TestCase
{
    private ContainerInterface $container;

    private MockObject&EventDispatcherInterface $dispatcher;

    private MockObject&FlowFactory $flowFactory;

    private MockObject&Connection $connection;

    private MockObject&LoggerInterface $logger;

    private FlowDispatcher $flowDispatcher;

    protected function setUp(): void
    {
        $this->container = new ContainerBuilder();
        $this->dispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->flowFactory = $this->createMock(FlowFactory::class);
        $this->connection = $this->createMock(Connection::class);

        $this->container->set('logger', $this->logger);
        $this->container->set(FlowFactory::class, $this->flowFactory);
        $this->container->set(Connection::class, $this->connection);

        $this->flowDispatcher = new FlowDispatcher($this->dispatcher, $this->container);
    }

    public function testDispatchWithNotFlowEventAware(): void
    {
        $event = $this->createCheckoutOrderPlacedEvent(new OrderEntity());

        $this->dispatcher->expects(static::once())->method('dispatch');
        $this->flowDispatcher->dispatch($event);
    }

    public function testDispatchSkipTrigger(): void
    {
        $event = $this->createCheckoutOrderPlacedEvent(new OrderEntity());

        $context = $event->getContext();
        $context->addState('skipTriggerFlow');

        $flowLogEvent = new FlowLogEvent(FlowLogEvent::NAME, $event);
        $this->dispatcher->expects(static::exactly(2))
            ->method('dispatch')
            ->willReturnOnConsecutiveCalls($event, $flowLogEvent);

        $this->flowDispatcher->dispatch($event);
    }

    public function testDispatchWithoutFlows(): void
    {
        $event = $this->createCheckoutOrderPlacedEvent(new OrderEntity());

        $flowLogEvent = new FlowLogEvent(FlowLogEvent::NAME, $event);
        $this->dispatcher->expects(static::exactly(2))
            ->method('dispatch')
            ->willReturnOnConsecutiveCalls($event, $flowLogEvent);

        $flow = new StorableFlow('state_enter.order.state.in_progress', $event->getContext(), [], []);
        $this->flowFactory->expects(static::once())
            ->method('create')
            ->willReturn($flow);

        $flowLoader = $this->createMock(FlowLoader::class);
        $this->container->set(FlowLoader::class, $flowLoader);
        $flowLoader->expects(static::once())
            ->method('load')
            ->willReturn([]);

        $this->flowDispatcher->dispatch($event);
    }

    /**
     * @param array<string, mixed> $flows
     */
    #[DataProvider('flowsData')]
    public function testDispatch(array $flows): void
    {
        $event = $this->createCheckoutOrderPlacedEvent(new OrderEntity());

        $flowLogEvent = new FlowLogEvent(FlowLogEvent::NAME, $event);
        $this->dispatcher->expects(static::exactly(2))
            ->method('dispatch')
            ->willReturnOnConsecutiveCalls($event, $flowLogEvent);

        $flow = new StorableFlow('state_enter.order.state.in_progress', $event->getContext(), [], []);
        $this->flowFactory->expects(static::once())
            ->method('create')
            ->willReturn($flow);

        $flowLoader = $this->createMock(FlowLoader::class);
        $flowLoader->expects(static::once())
            ->method('load')
            ->willReturn($flows);

        $flowExecutor = $this->createMock(FlowExecutor::class);
        $flowExecutor->expects(static::exactly(is_countable($flows['state_enter.order.state.in_progress']) ? \count($flows['state_enter.order.state.in_progress']) : 0))
            ->method('execute');

        $this->container->set(FlowLoader::class, $flowLoader);
        $this->container->set(FlowExecutor::class, $flowExecutor);

        $this->flowDispatcher->dispatch($event);
    }

    public function testNestedTransactionExceptionsAreRethrownWhenSavePointsAreNotEnabled(): void
    {
        $event = $this->createCheckoutOrderPlacedEvent(new OrderEntity());

        $this->dispatcher->method('dispatch')->willReturnOnConsecutiveCalls(
            $event,
            new FlowLogEvent(FlowLogEvent::NAME, $event),
        );

        $flow = new StorableFlow('state_enter.order.state.in_progress', $event->getContext(), [], []);
        $this->flowFactory->method('create')->willReturn($flow);

        $flowLoader = $this->createMock(FlowLoader::class);
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
        $flowExecutor->expects(static::once())
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

        $this->expectException(FlowException::class);
        $this->expectExceptionMessage('Flow action transaction could not be committed and was rolled back. Exception: An exception occurred in the driver: Table not found');

        $this->logger->expects(static::once())
            ->method('warning')
            ->with(
                "Could not execute flow with error message:\nFlow name: Order enters status in progress\nFlow id: flow-1\nSequence id: sequence-1\nFlow action transaction could not be committed and was rolled back. Exception: An exception occurred in the driver: Table not found\nError Code: 0\n",
                static::callback(static function (array $context) {
                    return $context['exception'] instanceof ExecuteSequenceException;
                })
            );

        $this->flowDispatcher->dispatch($event);
    }

    public function testExceptionsAreLoggedAndExecutionContinuesWhenNestedTransactionsWithSavePointsIsEnabled(): void
    {
        $event = $this->createCheckoutOrderPlacedEvent(new OrderEntity());

        $this->dispatcher->method('dispatch')->willReturnOnConsecutiveCalls(
            $event,
            new FlowLogEvent(FlowLogEvent::NAME, $event),
        );

        $flow = new StorableFlow('state_enter.order.state.in_progress', $event->getContext(), [], []);
        $this->flowFactory->method('create')->willReturn($flow);

        $flowLoader = $this->createMock(FlowLoader::class);
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
        $flowExecutor->expects(static::once())
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

        $this->connection->method('getTransactionNestingLevel')->willReturn(1);
        $this->connection->method('getNestTransactionsWithSavepoints')->willReturn(true);

        $this->logger->expects(static::once())
            ->method('warning')
            ->with(
                "Could not execute flow with error message:\nFlow name: Order enters status in progress\nFlow id: flow-1\nSequence id: sequence-1\nFlow action transaction could not be committed and was rolled back. Exception: An exception occurred in the driver: Table not found\nError Code: 0\n",
                static::callback(static function (array $context) {
                    return $context['exception'] instanceof ExecuteSequenceException;
                })
            );

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
        $context = Generator::createSalesChannelContext();

        return new CheckoutOrderPlacedEvent(
            $context->getContext(),
            $order,
            $context->getSalesChannelId(),
            null,
            $context
        );
    }
}
