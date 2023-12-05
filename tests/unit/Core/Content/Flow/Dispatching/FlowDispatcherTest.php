<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Flow\Dispatching;

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
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\FlowLogEvent;
use Shopware\Core\Framework\Test\TestDataCollection;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @package business-ops
 *
 * @internal
 *
 * @covers \Shopware\Core\Content\Flow\Dispatching\FlowDispatcher
 */
class FlowDispatcherTest extends TestCase
{
    private TestDataCollection $ids;

    private MockObject&ContainerInterface $container;

    private MockObject&EventDispatcherInterface $dispatcher;

    private MockObject&FlowFactory $flowFactory;

    private FlowDispatcher $flowDispatcher;

    protected function setUp(): void
    {
        $this->ids = new TestDataCollection();
        $this->container = $this->createMock(ContainerInterface::class);
        $this->dispatcher = $this->createMock(EventDispatcherInterface::class);
        $logger = $this->createMock(LoggerInterface::class);
        $this->flowFactory = $this->createMock(FlowFactory::class);

        $this->flowDispatcher = new FlowDispatcher($this->dispatcher, $logger, $this->flowFactory);
        $this->flowDispatcher->setContainer($this->container);
    }

    public function testDispatchWithNotFlowEventAware(): void
    {
        $order = new OrderEntity();
        $event = new CheckoutOrderPlacedEvent(
            Context::createDefaultContext(),
            $order,
            Defaults::SALES_CHANNEL_TYPE_STOREFRONT
        );

        $this->dispatcher->expects(static::once())->method('dispatch');
        $this->flowDispatcher->dispatch($event);
    }

    public function testDispatchSkipTrigger(): void
    {
        $context = Context::createDefaultContext();
        $context->addState('skipTriggerFlow');
        $order = new OrderEntity();
        $event = new CheckoutOrderPlacedEvent(
            $context,
            $order,
            Defaults::SALES_CHANNEL_TYPE_STOREFRONT
        );

        $flowLogEvent = new FlowLogEvent(FlowLogEvent::NAME, $event);
        $this->dispatcher->expects(static::exactly(2))
            ->method('dispatch')
            ->willReturnOnConsecutiveCalls($event, $flowLogEvent);

        $this->flowDispatcher->dispatch($event);
    }

    public function testDispatchWithoutFlowLoader(): void
    {
        $context = Context::createDefaultContext();
        $order = new OrderEntity();
        $event = new CheckoutOrderPlacedEvent(
            $context,
            $order,
            Defaults::SALES_CHANNEL_TYPE_STOREFRONT
        );

        $flowLogEvent = new FlowLogEvent(FlowLogEvent::NAME, $event);
        $this->dispatcher->expects(static::exactly(2))
            ->method('dispatch')
            ->willReturnOnConsecutiveCalls($event, $flowLogEvent);

        $flow = new StorableFlow('name', $context, [], []);
        $this->flowFactory->expects(static::once())
            ->method('create')
            ->willReturn($flow);

        $this->container->expects(static::exactly(1))
            ->method('get')
            ->willReturnOnConsecutiveCalls(null);

        static::expectException(ServiceNotFoundException::class);
        $this->flowDispatcher->dispatch($event);
    }

    public function testDispatchWithoutFlows(): void
    {
        $context = Context::createDefaultContext();
        $order = new OrderEntity();
        $event = new CheckoutOrderPlacedEvent(
            $context,
            $order,
            Defaults::SALES_CHANNEL_TYPE_STOREFRONT
        );

        $flowLogEvent = new FlowLogEvent(FlowLogEvent::NAME, $event);
        $this->dispatcher->expects(static::exactly(2))
            ->method('dispatch')
            ->willReturnOnConsecutiveCalls($event, $flowLogEvent);

        $flow = new StorableFlow('state_enter.order.state.in_progress', $context, [], []);
        $this->flowFactory->expects(static::once())
            ->method('create')
            ->willReturn($flow);

        $flowLoader = $this->createMock(FlowLoader::class);
        $flowLoader->expects(static::once())
            ->method('load')
            ->willReturn([]);

        $this->container->expects(static::exactly(1))
            ->method('get')
            ->willReturnOnConsecutiveCalls($flowLoader);

        $this->flowDispatcher->dispatch($event);
    }

    public function testDispatchWithoutFlowExecutor(): void
    {
        $context = Context::createDefaultContext();
        $order = new OrderEntity();
        $event = new CheckoutOrderPlacedEvent(
            $context,
            $order,
            Defaults::SALES_CHANNEL_TYPE_STOREFRONT
        );

        $flowLogEvent = new FlowLogEvent(FlowLogEvent::NAME, $event);
        $this->dispatcher->expects(static::exactly(2))
            ->method('dispatch')
            ->willReturnOnConsecutiveCalls($event, $flowLogEvent);

        $flow = new StorableFlow('state_enter.order.state.in_progress', $context, [], []);
        $this->flowFactory->expects(static::once())
            ->method('create')
            ->willReturn($flow);

        $flowLoader = $this->createMock(FlowLoader::class);
        $flowLoader->expects(static::once())
            ->method('load')
            ->willReturn([
                'state_enter.order.state.in_progress' => [
                    [
                        'id' => $this->ids->get('order'),
                        'name' => 'Order enters status in progress',
                        'payload' => [],
                    ],
                ],
            ]);

        $this->container->expects(static::exactly(2))
            ->method('get')
            ->willReturnOnConsecutiveCalls($flowLoader, null);

        static::expectException(ServiceNotFoundException::class);
        $this->flowDispatcher->dispatch($event);
    }

    /**
     * @param array<string, mixed> $flows
     *
     * @dataProvider flowsData
     */
    public function testDispatch(array $flows): void
    {
        $context = Context::createDefaultContext();
        $order = new OrderEntity();
        $event = new CheckoutOrderPlacedEvent(
            $context,
            $order,
            Defaults::SALES_CHANNEL_TYPE_STOREFRONT
        );

        $flowLogEvent = new FlowLogEvent(FlowLogEvent::NAME, $event);
        $this->dispatcher->expects(static::exactly(2))
            ->method('dispatch')
            ->willReturnOnConsecutiveCalls($event, $flowLogEvent);

        $flow = new StorableFlow('state_enter.order.state.in_progress', $context, [], []);
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

        $this->container->expects(static::exactly(2))
            ->method('get')
            ->willReturnOnConsecutiveCalls($flowLoader, $flowExecutor);

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

    public function testThrowsOnExceptions(): void
    {
        $context = Context::createDefaultContext();
        $order = new OrderEntity();
        $event = new CheckoutOrderPlacedEvent(
            $context,
            $order,
            Defaults::SALES_CHANNEL_TYPE_STOREFRONT
        );

        $this->dispatcher->method('dispatch')->willReturnOnConsecutiveCalls(
            $event,
            new FlowLogEvent(FlowLogEvent::NAME, $event),
        );

        $flow = new StorableFlow('state_enter.order.state.in_progress', $context, [], []);
        $this->flowFactory->method('create')->willReturn($flow);

        $flowLoader = $this->createMock(FlowLoader::class);
        $flowLoader->method('load')->willReturn([
            'state_enter.order.state.in_progress' => [
                [
                    'id' => 'flow-1',
                    'name' => 'Order enters status in progress',
                    'payload' => new Flow(Uuid::randomHex()),
                ],
                [
                    'id' => 'flow-2',
                    'name' => 'Some flows',
                    'payload' => new Flow(Uuid::randomHex()),
                ],
            ],
        ]);

        $flowExecutor = $this->createMock(FlowExecutor::class);
        $nthCall = 0;
        $flowExecutor->method('execute')->willReturnCallback(function () use (&$nthCall): void {
            ++$nthCall;

            throw match ($nthCall) {
                1 => new ExecuteSequenceException('flow-1', 'sequence-1', 'flow-1 failed'),
                2 => new \Exception('flow-2 failed'),
                default => new \LogicException('did not expect more than 2 calls'),
            };
        });

        $this->container->method('get')->willReturnOnConsecutiveCalls($flowLoader, $flowExecutor);

        $this->expectException(FlowException::class);
        $this->expectExceptionMessage(
            "Could not execute flows:\n"
            . "Could not execute flow with error message: Flow name: \"Order enters status in progress\" Flow id: \"flow-1\" Sequence id: sequence-1 Error Message: flow-1 failed Error Code: \"0\"\n"
            . 'Could not execute flow with error message: Flow name: "Some flows" Flow id: "flow-2" Error Message: flow-2 failed Error Code: "0"',
        );

        $this->flowDispatcher->dispatch($event);
    }
}
