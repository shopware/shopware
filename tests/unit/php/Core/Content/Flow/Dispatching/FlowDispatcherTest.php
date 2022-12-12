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

    /**
     * @var MockObject|ContainerInterface
     */
    private $container;

    /**
     * @var MockObject|EventDispatcherInterface
     */
    private $dispatcher;

    /**
     * @var MockObject|LoggerInterface
     */
    private $logger;

    /**
     * @var MockObject|FlowFactory
     */
    private $flowFactory;

    private FlowDispatcher $flowDispatcher;

    public function setUp(): void
    {
        $this->ids = new TestDataCollection();
        $this->container = $this->createMock(ContainerInterface::class);
        $this->dispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->flowFactory = $this->createMock(FlowFactory::class);

        $this->flowDispatcher = new FlowDispatcher($this->dispatcher, $this->logger, $this->flowFactory);
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
        $flowExecutor->expects(static::exactly(\count($flows['state_enter.order.state.in_progress'])))
            ->method('execute');

        $this->container->expects(static::exactly(2))
            ->method('get')
            ->willReturnOnConsecutiveCalls($flowLoader, $flowExecutor);

        $this->flowDispatcher->dispatch($event);
    }

    public function flowsData(): \Generator
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
}
