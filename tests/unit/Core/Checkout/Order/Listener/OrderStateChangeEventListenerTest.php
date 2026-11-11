<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Order\Listener;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\Event\OrderDeliveryStateMachineStateChangeEvent;
use Shopware\Core\Checkout\Order\Event\OrderStateMachineStateChangeEvent;
use Shopware\Core\Checkout\Order\Listener\OrderStateChangeEventListener;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\OrderException;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Content\Flow\Dispatching\Aware\OrderDeliveryAware;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Event\BusinessEventCollector;
use Shopware\Core\Framework\Event\BusinessEventCollectorEvent;
use Shopware\Core\Framework\Event\BusinessEventCollectorResponse;
use Shopware\Core\Framework\Event\BusinessEventDefinition;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateCollection;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;
use Shopware\Core\System\StateMachine\Event\StateMachineStateChangeEvent;
use Shopware\Core\System\StateMachine\StateMachineEntity;
use Shopware\Core\System\StateMachine\Transition;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Core\Test\Stub\EventDispatcher\CollectingEventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(OrderStateChangeEventListener::class)]
class OrderStateChangeEventListenerTest extends TestCase
{
    public function testGetSubscribedEvents(): void
    {
        $expected = [
            'state_machine.order.state_changed' => 'onOrderStateChange',
            'state_machine.order_delivery.state_changed' => 'onOrderDeliveryStateChange',
            'state_machine.order_transaction.state_changed' => 'onOrderTransactionStateChange',
            'state_machine.order_transaction_capture.state_changed' => 'onOrderTransactionCaptureStateChange',
            'state_machine.order_transaction_capture_refund.state_changed' => 'onOrderTransactionCaptureRefundStateChange',
            BusinessEventCollectorEvent::NAME => 'onAddStateEvents',
        ];

        static::assertSame($expected, OrderStateChangeEventListener::getSubscribedEvents());
    }

    public function testOnOrderDeliveryStateChange(): void
    {
        $stateMachine = new StateMachineEntity();
        $stateMachine->setTechnicalName('order_delivery');
        $previousState = new StateMachineStateEntity();
        $previousState->setTechnicalName('previous_state');
        $nextState = new StateMachineStateEntity();
        $nextState->setTechnicalName('next_state');

        $event = new StateMachineStateChangeEvent(
            $context = Context::createDefaultContext(),
            'enter',
            new Transition('order_delivery', 'order_delivery_id', 'transition_name', 'state_field_name'),
            $stateMachine,
            $previousState,
            $nextState
        );

        $expectedCriteria = new Criteria(['order_delivery_id']);
        $expectedCriteria->addAssociation('order.orderCustomer');
        $expectedCriteria->addAssociation('order.transactions.stateMachineState');

        $order = new OrderEntity();
        $order->setId('order_id');
        $order->setItemRounding(new CashRoundingConfig(2, 0.01, true));
        $order->setCurrencyId('currency_id');
        $order->setLanguageId('language_id');
        $order->setCurrencyFactor(1.0);
        $order->setTaxStatus('free');

        $delivery = new OrderDeliveryEntity();
        $delivery->setId('order_delivery_id');
        $delivery->setOrderId('order_id');
        $delivery->setOrder($order);

        $otherDelivery = new OrderDeliveryEntity();
        $otherDelivery->setId('other_order_delivery_id');
        $changedDelivery = new OrderDeliveryEntity();
        $changedDelivery->setId('order_delivery_id');
        $order->setDeliveries(new OrderDeliveryCollection([$otherDelivery, $changedDelivery]));

        $result = new EntitySearchResult(
            'order_delivery',
            1,
            new OrderDeliveryCollection([$delivery]),
            null,
            $expectedCriteria,
            $context
        );

        $repo = $this->createMock(EntityRepository::class);
        $repo
            ->expects($this->once())
            ->method('search')
            ->with(static::equalTo($expectedCriteria), $context)
            ->willReturn($result);

        $orderCriteria = [];
        $orderRepo = $this->createMock(EntityRepository::class);
        $orderRepo
            ->expects($this->exactly(2))
            ->method('search')
            ->willReturnCallback(static function (Criteria $criteria) use (&$orderCriteria, $order, $context): EntitySearchResult {
                $orderCriteria[] = $criteria;

                return new EntitySearchResult('order', 1, new OrderCollection([$order]), null, $criteria, $context);
            });

        $dispatched = [];
        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher
            ->expects($this->exactly(2))
            ->method('dispatch')
            ->willReturnCallback(static function (object $event, ?string $name) use (&$dispatched): object {
                $dispatched[$name ?? $event::class] = $event;

                return $event;
            });

        $listener = new OrderStateChangeEventListener(
            $orderRepo,
            static::createStub(EntityRepository::class),
            $repo,
            $dispatcher,
            static::createStub(BusinessEventCollector::class),
            static::createStub(EntityRepository::class)
        );

        $listener->onOrderDeliveryStateChange($event);

        $stateEvent = $dispatched[$event->getStateEventName()] ?? null;
        static::assertInstanceOf(OrderDeliveryStateMachineStateChangeEvent::class, $stateEvent);
        static::assertSame($order, $stateEvent->getOrder());
        static::assertSame($changedDelivery, $stateEvent->getOrderDelivery());
        static::assertSame('order_delivery_id', $stateEvent->getOrderDeliveryId());

        $loadedOrderCriteria = $orderCriteria[1] ?? null;
        static::assertInstanceOf(Criteria::class, $loadedOrderCriteria);
        static::assertTrue($loadedOrderCriteria->hasAssociation('deliveries'));
        $deliveryCriteria = $loadedOrderCriteria->getAssociation('deliveries');
        static::assertTrue($deliveryCriteria->hasAssociation('stateMachineState'));
        static::assertTrue($deliveryCriteria->hasAssociation('positions'));
        static::assertTrue($deliveryCriteria->getAssociation('positions')->hasAssociation('orderLineItem'));
    }

    public function testOnOrderDeliveryStateChangeThrowsWhenTheOrderCriteriaLeavesTheDeliveryOut(): void
    {
        $order = new OrderEntity();
        $order->setId('order_id');
        $order->setItemRounding(new CashRoundingConfig(2, 0.01, true));
        $order->setCurrencyId('currency_id');
        $order->setLanguageId('language_id');
        $order->setCurrencyFactor(1.0);
        $order->setTaxStatus('free');
        $order->setDeliveries(new OrderDeliveryCollection());

        $delivery = new OrderDeliveryEntity();
        $delivery->setId('order_delivery_id');
        $delivery->setOrderId('order_id');
        $delivery->setOrder($order);

        $listener = new OrderStateChangeEventListener(
            new StaticEntityRepository([new OrderCollection([$order]), new OrderCollection([$order])]),
            static::createStub(EntityRepository::class),
            new StaticEntityRepository([new OrderDeliveryCollection([$delivery])]),
            static::createStub(EventDispatcherInterface::class),
            static::createStub(BusinessEventCollector::class),
            static::createStub(EntityRepository::class)
        );

        $this->expectExceptionObject(OrderException::orderDeliveryNotFound('order_delivery_id'));

        $listener->onOrderDeliveryStateChange($this->createEnterStateEvent(
            new Transition('order_delivery', 'order_delivery_id', 'transition_name', 'state_field_name'),
            'order_delivery',
            'next_state'
        ));
    }

    public function testOnOrderDeliveryStateChangeNotFound(): void
    {
        $result = new EntitySearchResult(
            'order_delivery',
            0,
            new OrderDeliveryCollection(),
            null,
            new Criteria(),
            Context::createDefaultContext()
        );

        $repo = $this->createMock(EntityRepository::class);
        $repo
            ->expects($this->once())
            ->method('search')
            ->willReturn($result);

        $this->expectException(OrderException::class);

        $listener = new OrderStateChangeEventListener(
            static::createStub(EntityRepository::class),
            static::createStub(EntityRepository::class),
            $repo,
            static::createStub(EventDispatcherInterface::class),
            static::createStub(BusinessEventCollector::class),
            static::createStub(EntityRepository::class)
        );

        $stateMachine = new StateMachineEntity();
        $stateMachine->setTechnicalName('order_delivery');
        $previousState = new StateMachineStateEntity();
        $previousState->setTechnicalName('previous_state');
        $nextState = new StateMachineStateEntity();
        $nextState->setTechnicalName('next_state');

        $event = new StateMachineStateChangeEvent(
            Context::createDefaultContext(),
            'enter',
            new Transition('order_delivery', 'order_delivery_id', 'transition_name', 'state_field_name'),
            $stateMachine,
            $previousState,
            $nextState
        );

        $listener->onOrderDeliveryStateChange($event);
    }

    public function testOnOrderDeliveryStateChangeWithoutOrder(): void
    {
        $delivery = new OrderDeliveryEntity();
        $delivery->setId('order_delivery_id');
        $delivery->setOrderId('order_id');

        $result = new EntitySearchResult(
            'order_delivery',
            1,
            new OrderDeliveryCollection([$delivery]),
            null,
            new Criteria(),
            Context::createDefaultContext()
        );

        $repo = $this->createMock(EntityRepository::class);
        $repo
            ->expects($this->once())
            ->method('search')
            ->willReturn($result);

        $this->expectException(OrderException::class);

        $listener = new OrderStateChangeEventListener(
            static::createStub(EntityRepository::class),
            static::createStub(EntityRepository::class),
            $repo,
            static::createStub(EventDispatcherInterface::class),
            static::createStub(BusinessEventCollector::class),
            static::createStub(EntityRepository::class)
        );

        $stateMachine = new StateMachineEntity();
        $stateMachine->setTechnicalName('order_delivery');
        $previousState = new StateMachineStateEntity();
        $previousState->setTechnicalName('previous_state');
        $nextState = new StateMachineStateEntity();
        $nextState->setTechnicalName('next_state');

        $event = new StateMachineStateChangeEvent(
            Context::createDefaultContext(),
            'enter',
            new Transition('order_delivery', 'order_delivery_id', 'transition_name', 'state_field_name'),
            $stateMachine,
            $previousState,
            $nextState
        );

        $listener->onOrderDeliveryStateChange($event);
    }

    public function testOnOrderTransactionStateChange(): void
    {
        $expectedCriteria = new Criteria(['order_transaction_id']);
        $expectedCriteria->addAssociation('paymentMethod');
        $expectedCriteria->addAssociation('order.orderCustomer');
        $expectedCriteria->addAssociation('order.transactions.stateMachineState');

        $order = new OrderEntity();
        $order->setId('order_id');
        $order->setItemRounding(new CashRoundingConfig(2, 0.01, true));
        $order->setCurrencyId('currency_id');
        $order->setLanguageId('language_id');
        $order->setCurrencyFactor(1.0);
        $order->setTaxStatus('free');

        $paymentMethod = new PaymentMethodEntity();
        $paymentMethod->setId('payment_method_id');

        $transaction = new OrderTransactionEntity();
        $transaction->setId('order_transaction_id');
        $transaction->setOrderId('order_id');
        $transaction->setOrder($order);
        $transaction->setPaymentMethod($paymentMethod);

        $result = new EntitySearchResult(
            'order_transaction',
            1,
            new OrderTransactionCollection([$transaction]),
            null,
            $expectedCriteria,
            $context = Context::createDefaultContext()
        );

        $repo = $this->createMock(EntityRepository::class);
        $repo
            ->expects($this->once())
            ->method('search')
            ->with(static::equalTo($expectedCriteria), $context)
            ->willReturn($result);

        $orderRepo = $this->createMock(EntityRepository::class);
        $orderRepo
            ->expects($this->exactly(2))
            ->method('search')
            ->willReturn(new EntitySearchResult('order', 1, new OrderCollection([$order]), null, new Criteria(['order_id']), $context));

        $expectedEvent = new OrderStateMachineStateChangeEvent('enter.order_transaction.next_state', $order, $context);

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher
            ->expects($this->exactly(2))
            ->method('dispatch')
            ->with(static::callback(static function ($event) use ($expectedEvent): bool {
                if ($event instanceof OrderStateMachineStateChangeEvent) {
                    static::assertSame($expectedEvent->getOrder(), $event->getOrder());
                    static::assertNotInstanceOf(OrderDeliveryAware::class, $event);
                }

                return true;
            }));

        $stateMachine = new StateMachineEntity();
        $stateMachine->setTechnicalName('order_transaction');
        $previousState = new StateMachineStateEntity();
        $previousState->setTechnicalName('previous_state');
        $nextState = new StateMachineStateEntity();
        $nextState->setTechnicalName('next_state');

        $event = new StateMachineStateChangeEvent(
            Context::createDefaultContext(),
            'enter',
            new Transition('order_transaction', 'order_transaction_id', 'transition_name', 'state_field_name'),
            $stateMachine,
            $previousState,
            $nextState
        );

        $listener = new OrderStateChangeEventListener(
            $orderRepo,
            $repo,
            static::createStub(EntityRepository::class),
            $dispatcher,
            static::createStub(BusinessEventCollector::class),
            static::createStub(EntityRepository::class)
        );

        $listener->onOrderTransactionStateChange($event);
    }

    public function testOnOrderTransactionStateChangeWithoutTransaction(): void
    {
        $result = new EntitySearchResult(
            'order_transaction',
            0,
            new OrderTransactionCollection(),
            null,
            new Criteria(),
            Context::createDefaultContext()
        );

        $repo = $this->createMock(EntityRepository::class);
        $repo
            ->expects($this->once())
            ->method('search')
            ->willReturn($result);

        $this->expectException(OrderException::class);

        $listener = new OrderStateChangeEventListener(
            static::createStub(EntityRepository::class),
            $repo,
            static::createStub(EntityRepository::class),
            static::createStub(EventDispatcherInterface::class),
            static::createStub(BusinessEventCollector::class),
            static::createStub(EntityRepository::class)
        );

        $stateMachine = new StateMachineEntity();
        $stateMachine->setTechnicalName('order_delivery');
        $previousState = new StateMachineStateEntity();
        $previousState->setTechnicalName('previous_state');
        $nextState = new StateMachineStateEntity();
        $nextState->setTechnicalName('next_state');

        $event = new StateMachineStateChangeEvent(
            Context::createDefaultContext(),
            'enter',
            new Transition('order_delivery', 'order_delivery_id', 'transition_name', 'state_field_name'),
            $stateMachine,
            $previousState,
            $nextState
        );

        $listener->onOrderTransactionStateChange($event);
    }

    public function testOnOrderTransactionStateChangeWithoutPaymentMethod(): void
    {
        $transaction = new OrderTransactionEntity();
        $transaction->setId('order_transaction_id');

        $result = new EntitySearchResult(
            'order_transaction',
            1,
            new OrderTransactionCollection([$transaction]),
            null,
            new Criteria(),
            Context::createDefaultContext()
        );

        $repo = $this->createMock(EntityRepository::class);
        $repo
            ->expects($this->once())
            ->method('search')
            ->willReturn($result);

        $this->expectException(OrderException::class);

        $listener = new OrderStateChangeEventListener(
            static::createStub(EntityRepository::class),
            $repo,
            static::createStub(EntityRepository::class),
            static::createStub(EventDispatcherInterface::class),
            static::createStub(BusinessEventCollector::class),
            static::createStub(EntityRepository::class)
        );

        $stateMachine = new StateMachineEntity();
        $stateMachine->setTechnicalName('order_delivery');
        $previousState = new StateMachineStateEntity();
        $previousState->setTechnicalName('previous_state');
        $nextState = new StateMachineStateEntity();
        $nextState->setTechnicalName('next_state');

        $event = new StateMachineStateChangeEvent(
            Context::createDefaultContext(),
            'enter',
            new Transition('order_delivery', 'order_delivery_id', 'transition_name', 'state_field_name'),
            $stateMachine,
            $previousState,
            $nextState
        );

        $listener->onOrderTransactionStateChange($event);
    }

    public function testOnOrderTransactionStateChangeWithoutOrder(): void
    {
        $transaction = new OrderTransactionEntity();
        $transaction->setId('order_transaction_id');
        $transaction->setPaymentMethod(new PaymentMethodEntity());

        $result = new EntitySearchResult(
            'order_transaction',
            1,
            new OrderTransactionCollection([$transaction]),
            null,
            new Criteria(),
            Context::createDefaultContext()
        );

        $repo = $this->createMock(EntityRepository::class);
        $repo
            ->expects($this->once())
            ->method('search')
            ->willReturn($result);

        $this->expectException(OrderException::class);

        $listener = new OrderStateChangeEventListener(
            static::createStub(EntityRepository::class),
            $repo,
            static::createStub(EntityRepository::class),
            static::createStub(EventDispatcherInterface::class),
            static::createStub(BusinessEventCollector::class),
            static::createStub(EntityRepository::class)
        );

        $stateMachine = new StateMachineEntity();
        $stateMachine->setTechnicalName('order_delivery');
        $previousState = new StateMachineStateEntity();
        $previousState->setTechnicalName('previous_state');
        $nextState = new StateMachineStateEntity();
        $nextState->setTechnicalName('next_state');

        $event = new StateMachineStateChangeEvent(
            Context::createDefaultContext(),
            'enter',
            new Transition('order_delivery', 'order_delivery_id', 'transition_name', 'state_field_name'),
            $stateMachine,
            $previousState,
            $nextState
        );

        $listener->onOrderTransactionStateChange($event);
    }

    public function testOnOrderStateChange(): void
    {
        $order = new OrderEntity();
        $order->setId('order_id');
        $order->setItemRounding(new CashRoundingConfig(2, 0.01, true));
        $order->setCurrencyId('currency_id');
        $order->setLanguageId('language_id');
        $order->setCurrencyFactor(1.0);
        $order->setTaxStatus('free');

        $orderRepo = $this->createMock(EntityRepository::class);
        $orderRepo
            ->expects($this->exactly(2))
            ->method('search')
            ->willReturn(new EntitySearchResult('order', 1, new OrderCollection([$order]), null, new Criteria(['order_id']), Context::createDefaultContext()));

        $expectedEvent = new OrderStateMachineStateChangeEvent('enter.order_transaction.next_state', $order, Context::createDefaultContext());

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher
            ->expects($this->exactly(2))
            ->method('dispatch')
            ->with(static::callback(static function ($event) use ($expectedEvent): bool {
                if ($event instanceof OrderStateMachineStateChangeEvent) {
                    static::assertSame($expectedEvent->getOrder(), $event->getOrder());
                    static::assertNotInstanceOf(OrderDeliveryAware::class, $event);
                }

                return true;
            }));

        $stateMachine = new StateMachineEntity();
        $stateMachine->setTechnicalName('order');
        $previousState = new StateMachineStateEntity();
        $previousState->setTechnicalName('previous_state');
        $nextState = new StateMachineStateEntity();
        $nextState->setTechnicalName('next_state');

        $event = new StateMachineStateChangeEvent(
            Context::createDefaultContext(),
            'enter',
            new Transition('order', 'order_id', 'transition_name', 'state_field_name'),
            $stateMachine,
            $previousState,
            $nextState
        );

        $listener = new OrderStateChangeEventListener(
            $orderRepo,
            static::createStub(EntityRepository::class),
            static::createStub(EntityRepository::class),
            $dispatcher,
            static::createStub(BusinessEventCollector::class),
            static::createStub(EntityRepository::class)
        );

        $listener->onOrderStateChange($event);
    }

    public function testOnOrderTransactionCaptureStateChange(): void
    {
        $order = $this->createOrder();
        $dispatcher = new CollectingEventDispatcher();

        $listener = new OrderStateChangeEventListener(
            new StaticEntityRepository([[$order->getId()], new OrderCollection([$order]), new OrderCollection([$order])]),
            static::createStub(EntityRepository::class),
            static::createStub(EntityRepository::class),
            $dispatcher,
            static::createStub(BusinessEventCollector::class),
            static::createStub(EntityRepository::class)
        );

        $listener->onOrderTransactionCaptureStateChange($this->createEnterStateEvent(
            new Transition('order_transaction_capture', 'capture_id', 'complete', 'stateId'),
            stateMachine: 'order_transaction_capture.state',
            state: 'completed',
        ));

        $stateEvent = $dispatcher->getEvents()['state_enter.order_transaction_capture.state.completed'] ?? null;
        static::assertInstanceOf(OrderStateMachineStateChangeEvent::class, $stateEvent);
        static::assertSame($order, $stateEvent->getOrder());
    }

    public function testOnOrderTransactionCaptureStateChangeWithoutOrder(): void
    {
        $listener = new OrderStateChangeEventListener(
            StaticEntityRepository::of(OrderCollection::class, [[]]),
            static::createStub(EntityRepository::class),
            static::createStub(EntityRepository::class),
            static::createStub(EventDispatcherInterface::class),
            static::createStub(BusinessEventCollector::class),
            static::createStub(EntityRepository::class)
        );

        $this->expectExceptionObject(OrderException::orderTransactionCaptureNotFound('capture_id'));

        $listener->onOrderTransactionCaptureStateChange($this->createEnterStateEvent(
            new Transition('order_transaction_capture', 'capture_id', 'complete', 'stateId'),
            stateMachine: 'order_transaction_capture.state',
            state: 'completed',
        ));
    }

    public function testOnOrderTransactionCaptureRefundStateChange(): void
    {
        $order = $this->createOrder();
        $dispatcher = new CollectingEventDispatcher();

        $listener = new OrderStateChangeEventListener(
            new StaticEntityRepository([[$order->getId()], new OrderCollection([$order]), new OrderCollection([$order])]),
            static::createStub(EntityRepository::class),
            static::createStub(EntityRepository::class),
            $dispatcher,
            static::createStub(BusinessEventCollector::class),
            static::createStub(EntityRepository::class)
        );

        $listener->onOrderTransactionCaptureRefundStateChange($this->createEnterStateEvent(
            new Transition('order_transaction_capture_refund', 'refund_id', 'complete', 'stateId'),
            stateMachine: 'order_transaction_capture_refund.state',
            state: 'completed',
        ));

        $stateEvent = $dispatcher->getEvents()['state_enter.order_transaction_capture_refund.state.completed'] ?? null;
        static::assertInstanceOf(OrderStateMachineStateChangeEvent::class, $stateEvent);
        static::assertSame($order, $stateEvent->getOrder());
    }

    public function testOnOrderTransactionCaptureRefundStateChangeWithoutOrder(): void
    {
        $listener = new OrderStateChangeEventListener(
            StaticEntityRepository::of(OrderCollection::class, [[]]),
            static::createStub(EntityRepository::class),
            static::createStub(EntityRepository::class),
            static::createStub(EventDispatcherInterface::class),
            static::createStub(BusinessEventCollector::class),
            static::createStub(EntityRepository::class)
        );

        $this->expectExceptionObject(OrderException::orderTransactionCaptureRefundNotFound('refund_id'));

        $listener->onOrderTransactionCaptureRefundStateChange($this->createEnterStateEvent(
            new Transition('order_transaction_capture_refund', 'refund_id', 'complete', 'stateId'),
            stateMachine: 'order_transaction_capture_refund.state',
            state: 'completed',
        ));
    }

    /**
     * @param array<string, string|null> $states technical name of the state, mapped to the technical name of its state machine or null when it has none
     * @param array<string, class-string> $expectedEvents event class per collected event name
     */
    #[DataProvider('addStateEventsProvider')]
    public function testOnAddStateEvents(array $states, array $expectedEvents): void
    {
        $context = Context::createDefaultContext();

        $event = new BusinessEventCollectorEvent(
            new BusinessEventCollectorResponse(),
            $context
        );

        $stateEntities = [];
        foreach ($states as $technicalName => $stateMachineTechnicalName) {
            $state = new StateMachineStateEntity();
            $state->setId($technicalName);
            $state->setTechnicalName($technicalName);

            if ($stateMachineTechnicalName !== null) {
                $stateMachine = new StateMachineEntity();
                $stateMachine->setTechnicalName($stateMachineTechnicalName);
                $state->setStateMachine($stateMachine);
            }

            $stateEntities[] = $state;
        }

        $expectedCriteria = new Criteria();
        $expectedCriteria->addAssociation('stateMachine');

        $searchResult = new EntitySearchResult(
            'state_machine_state',
            \count($stateEntities),
            new StateMachineStateCollection($stateEntities),
            null,
            $expectedCriteria,
            $context
        );

        $stateRepo = $this->createMock(EntityRepository::class);
        $stateRepo
            ->expects($this->once())
            ->method('search')
            ->with(static::equalTo($expectedCriteria), $context)
            ->willReturn($searchResult);

        $collector = $this->createMock(BusinessEventCollector::class);
        $collector
            ->expects($this->exactly(\count($expectedEvents)))
            ->method('define')
            ->willReturnCallback(static fn (string $class, string $name): BusinessEventDefinition => new BusinessEventDefinition($name, $class, []));

        $listener = new OrderStateChangeEventListener(
            static::createStub(EntityRepository::class),
            static::createStub(EntityRepository::class),
            static::createStub(EntityRepository::class),
            static::createStub(EventDispatcherInterface::class),
            $collector,
            $stateRepo,
        );

        $listener->onAddStateEvents($event);

        static::assertSame(
            $expectedEvents,
            array_map(static fn (BusinessEventDefinition $definition): string => $definition->getClass(), $event->getCollection()->getElements())
        );
    }

    /**
     * @return iterable<string, array{0: array<string, string|null>, 1: array<string, class-string>}>
     */
    public static function addStateEventsProvider(): iterable
    {
        yield 'a state collects an event for the enter and the leave side' => [
            ['paid' => 'order'],
            [
                'state_enter.order.paid' => OrderStateMachineStateChangeEvent::class,
                'state_leave.order.paid' => OrderStateMachineStateChangeEvent::class,
            ],
        ];

        yield 'a state without a state machine is skipped, the states after it are still collected' => [
            ['open' => null, 'paid' => 'order'],
            [
                'state_enter.order.paid' => OrderStateMachineStateChangeEvent::class,
                'state_leave.order.paid' => OrderStateMachineStateChangeEvent::class,
            ],
        ];

        yield 'a delivery state collects the event that carries the delivery' => [
            ['shipped' => 'order_delivery.state'],
            [
                'state_enter.order_delivery.state.shipped' => OrderDeliveryStateMachineStateChangeEvent::class,
                'state_leave.order_delivery.state.shipped' => OrderDeliveryStateMachineStateChangeEvent::class,
            ],
        ];
    }

    private function createOrder(): OrderEntity
    {
        $order = new OrderEntity();
        $order->setId('order_id');
        $order->setItemRounding(new CashRoundingConfig(2, 0.01, true));
        $order->setCurrencyId('currency_id');
        $order->setLanguageId('language_id');
        $order->setCurrencyFactor(1.0);
        $order->setTaxStatus('free');

        return $order;
    }

    private function createEnterStateEvent(Transition $transition, string $stateMachine, string $state): StateMachineStateChangeEvent
    {
        $machine = new StateMachineEntity();
        $machine->setTechnicalName($stateMachine);
        $previousState = new StateMachineStateEntity();
        $previousState->setTechnicalName('previous_state');
        $nextState = new StateMachineStateEntity();
        $nextState->setTechnicalName($state);

        return new StateMachineStateChangeEvent(
            Context::createDefaultContext(),
            StateMachineStateChangeEvent::STATE_MACHINE_TRANSITION_SIDE_ENTER,
            $transition,
            $machine,
            $previousState,
            $nextState
        );
    }
}
