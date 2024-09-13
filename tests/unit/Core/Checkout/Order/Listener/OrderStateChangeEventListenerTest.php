<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Order\Listener;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\Event\OrderStateMachineStateChangeEvent;
use Shopware\Core\Checkout\Order\Listener\OrderStateChangeEventListener;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\OrderException;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
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
            ->expects(static::once())
            ->method('search')
            ->with(static::equalTo($expectedCriteria), $context)
            ->willReturn($result);

        $orderRepo = $this->createMock(EntityRepository::class);
        $orderRepo
            ->expects(static::exactly(2))
            ->method('search')
            ->willReturn(new EntitySearchResult('order', 1, new OrderCollection([$order]), null, new Criteria(['order_id']), $context));

        $expectedEvent = new OrderStateMachineStateChangeEvent('enter.order_delivery.next_state', $order, $context);

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher
            ->expects(static::exactly(2))
            ->method('dispatch')
            ->with(static::callback(function ($event) use ($expectedEvent): bool {
                if ($event instanceof OrderStateMachineStateChangeEvent) {
                    static::assertEquals($expectedEvent->getOrder(), $event->getOrder());
                }

                return true;
            }));

        $listener = new OrderStateChangeEventListener(
            $orderRepo,
            $this->createMock(EntityRepository::class),
            $repo,
            $dispatcher,
            $this->createMock(BusinessEventCollector::class),
            $this->createMock(EntityRepository::class)
        );

        $listener->onOrderDeliveryStateChange($event);
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
            ->expects(static::once())
            ->method('search')
            ->willReturn($result);

        $this->expectException(OrderException::class);

        $listener = new OrderStateChangeEventListener(
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            $repo,
            $this->createMock(EventDispatcherInterface::class),
            $this->createMock(BusinessEventCollector::class),
            $this->createMock(EntityRepository::class)
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
            ->expects(static::once())
            ->method('search')
            ->willReturn($result);

        $this->expectException(OrderException::class);

        $listener = new OrderStateChangeEventListener(
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            $repo,
            $this->createMock(EventDispatcherInterface::class),
            $this->createMock(BusinessEventCollector::class),
            $this->createMock(EntityRepository::class)
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
            ->expects(static::once())
            ->method('search')
            ->with(static::equalTo($expectedCriteria), $context)
            ->willReturn($result);

        $orderRepo = $this->createMock(EntityRepository::class);
        $orderRepo
            ->expects(static::exactly(2))
            ->method('search')
            ->willReturn(new EntitySearchResult('order', 1, new OrderCollection([$order]), null, new Criteria(['order_id']), $context));

        $expectedEvent = new OrderStateMachineStateChangeEvent('enter.order_transaction.next_state', $order, $context);

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher
            ->expects(static::exactly(2))
            ->method('dispatch')
            ->with(static::callback(function ($event) use ($expectedEvent): bool {
                if ($event instanceof OrderStateMachineStateChangeEvent) {
                    static::assertEquals($expectedEvent->getOrder(), $event->getOrder());
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
            $this->createMock(EntityRepository::class),
            $dispatcher,
            $this->createMock(BusinessEventCollector::class),
            $this->createMock(EntityRepository::class)
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
            ->expects(static::once())
            ->method('search')
            ->willReturn($result);

        $this->expectException(OrderException::class);

        $listener = new OrderStateChangeEventListener(
            $this->createMock(EntityRepository::class),
            $repo,
            $this->createMock(EntityRepository::class),
            $this->createMock(EventDispatcherInterface::class),
            $this->createMock(BusinessEventCollector::class),
            $this->createMock(EntityRepository::class)
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
            ->expects(static::once())
            ->method('search')
            ->willReturn($result);

        $this->expectException(OrderException::class);

        $listener = new OrderStateChangeEventListener(
            $this->createMock(EntityRepository::class),
            $repo,
            $this->createMock(EntityRepository::class),
            $this->createMock(EventDispatcherInterface::class),
            $this->createMock(BusinessEventCollector::class),
            $this->createMock(EntityRepository::class)
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
            ->expects(static::once())
            ->method('search')
            ->willReturn($result);

        $this->expectException(OrderException::class);

        $listener = new OrderStateChangeEventListener(
            $this->createMock(EntityRepository::class),
            $repo,
            $this->createMock(EntityRepository::class),
            $this->createMock(EventDispatcherInterface::class),
            $this->createMock(BusinessEventCollector::class),
            $this->createMock(EntityRepository::class)
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
            ->expects(static::exactly(2))
            ->method('search')
            ->willReturn(new EntitySearchResult('order', 1, new OrderCollection([$order]), null, new Criteria(['order_id']), Context::createDefaultContext()));

        $expectedEvent = new OrderStateMachineStateChangeEvent('enter.order_transaction.next_state', $order, Context::createDefaultContext());

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher
            ->expects(static::exactly(2))
            ->method('dispatch')
            ->with(static::callback(function ($event) use ($expectedEvent): bool {
                if ($event instanceof OrderStateMachineStateChangeEvent) {
                    static::assertEquals($expectedEvent->getOrder(), $event->getOrder());
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
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            $dispatcher,
            $this->createMock(BusinessEventCollector::class),
            $this->createMock(EntityRepository::class)
        );

        $listener->onOrderStateChange($event);
    }

    public function testOnAddStateEvents(): void
    {
        $context = Context::createDefaultContext();

        $event = new BusinessEventCollectorEvent(
            new BusinessEventCollectorResponse(),
            Context::createDefaultContext()
        );

        $stateMachine = new StateMachineEntity();
        $stateMachine->setTechnicalName('order');

        $state = new StateMachineStateEntity();
        $state->setId('state_id');
        $state->setTechnicalName('paid');
        $state->setStateMachine($stateMachine);

        $expectedCriteria = new Criteria();
        $expectedCriteria->addAssociation('stateMachine');

        $states = new EntitySearchResult(
            'state_machine_state',
            1,
            new StateMachineStateCollection([$state]),
            null,
            $expectedCriteria,
            $context
        );

        $stateRepo = $this->createMock(EntityRepository::class);
        $stateRepo
            ->expects(static::once())
            ->method('search')
            ->with(static::equalTo($expectedCriteria), $context)
            ->willReturn($states);

        $definition = new BusinessEventDefinition(
            'enter.order.paid',
            OrderStateMachineStateChangeEvent::class,
            []
        );

        $collector = $this->createMock(BusinessEventCollector::class);
        $collector
            ->expects(static::exactly(2))
            ->method('define')
            ->with(OrderStateMachineStateChangeEvent::class, static::logicalOr(static::equalTo('state_enter.order.paid'), static::equalTo('state_leave.order.paid')))
            ->willReturnCallback(function (string $class, string $name): BusinessEventDefinition {
                return new BusinessEventDefinition($name, $class, []);
            });

        $listener = new OrderStateChangeEventListener(
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            $this->createMock(EventDispatcherInterface::class),
            $collector,
            $stateRepo,
        );

        $listener->onAddStateEvents($event);

        $events = $event->getCollection();
        static::assertCount(2, $events);
        static::assertArrayHasKey('state_enter.order.paid', $events->getElements());
        static::assertArrayHasKey('state_leave.order.paid', $events->getElements());
    }
}
