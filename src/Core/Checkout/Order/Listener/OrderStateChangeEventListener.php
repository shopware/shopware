<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Listener;

use Shopware\Core\Checkout\Cart\Exception\OrderDeliveryNotFoundException;
use Shopware\Core\Checkout\Cart\Exception\OrderNotFoundException;
use Shopware\Core\Checkout\Cart\Exception\OrderTransactionNotFoundException;
use Shopware\Core\Checkout\Cart\Order\OrderConverter;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Checkout\Order\Event\OrderStateChangeCriteriaEvent;
use Shopware\Core\Checkout\Order\Event\OrderStateMachineStateChangeEvent;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Event\BusinessEventCollector;
use Shopware\Core\Framework\Event\BusinessEventCollectorEvent;
use Shopware\Core\Framework\Feature;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;
use Shopware\Core\System\StateMachine\Event\StateMachineStateChangeEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class OrderStateChangeEventListener implements EventSubscriberInterface
{
    /**
     * @var EntityRepositoryInterface
     */
    private $stateRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $paymentRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $orderRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $transactionRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $deliveryRepository;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var OrderConverter
     */
    private $orderConverter;

    /**
     * @var BusinessEventCollector
     */
    private $businessEventCollector;

    public function __construct(
        EntityRepositoryInterface $orderRepository,
        EntityRepositoryInterface $transactionRepository,
        EntityRepositoryInterface $deliveryRepository,
        EventDispatcherInterface $eventDispatcher,
        OrderConverter $orderConverter,
        BusinessEventCollector $businessEventCollector,
        EntityRepositoryInterface $stateRepository,
        EntityRepositoryInterface $paymentRepository
    ) {
        $this->orderRepository = $orderRepository;
        $this->transactionRepository = $transactionRepository;
        $this->deliveryRepository = $deliveryRepository;
        $this->eventDispatcher = $eventDispatcher;
        $this->orderConverter = $orderConverter;
        $this->paymentRepository = $paymentRepository;
        $this->stateRepository = $stateRepository;
        $this->businessEventCollector = $businessEventCollector;
    }

    public static function getSubscribedEvents()
    {
        return [
            'state_machine.order.state_changed' => 'onOrderStateChange',
            'state_machine.order_delivery.state_changed' => 'onOrderDeliveryStateChange',
            'state_machine.order_transaction.state_changed' => 'onOrderTransactionStateChange',
            BusinessEventCollectorEvent::NAME => 'onAddStateEvents',
        ];
    }

    /**
     * @throws OrderDeliveryNotFoundException
     * @throws OrderNotFoundException
     */
    public function onOrderDeliveryStateChange(StateMachineStateChangeEvent $event): void
    {
        $orderDeliveryId = $event->getTransition()->getEntityId();
        $context = $event->getContext();

        /** @var OrderDeliveryEntity|null $orderDelivery */
        $orderDelivery = $this->deliveryRepository
            ->search(new Criteria([$orderDeliveryId]), $context)
            ->first();

        if ($orderDelivery === null) {
            throw new OrderDeliveryNotFoundException($orderDeliveryId);
        }

        $order = $this->getOrder($orderDelivery->getOrderId(), $event->getContext());

        if (!Feature::isActive('FEATURE_NEXT_9351')) {
            $this->dispatchEvent($event->getStateEventName(), $order, $event->getContext());

            return;
        }

        $context = $this->getContext($order, $event->getContext());

        $this->dispatchEvent($event->getStateEventName(), $order, $context);
    }

    /**
     * @throws OrderNotFoundException
     * @throws OrderTransactionNotFoundException
     */
    public function onOrderTransactionStateChange(StateMachineStateChangeEvent $event): void
    {
        $orderTransactionId = $event->getTransition()->getEntityId();

        $criteria = new Criteria([$orderTransactionId]);
        $criteria->addAssociation('paymentMethod');

        $orderTransaction = $this->transactionRepository
            ->search($criteria, $event->getContext())
            ->first();

        if (!$orderTransaction instanceof OrderTransactionEntity) {
            throw new OrderTransactionNotFoundException($orderTransactionId);
        }
        if (!$orderTransaction->getPaymentMethod() instanceof PaymentMethodEntity) {
            throw new OrderTransactionNotFoundException($orderTransactionId);
        }

        $order = $this->getOrder($orderTransaction->getOrderId(), $event->getContext());

        if (!Feature::isActive('FEATURE_NEXT_9351')) {
            $this->dispatchEvent($event->getStateEventName(), $order, $event->getContext());

            return;
        }

        $context = $this->getContext($order, $event->getContext());

        $this->dispatchEvent($event->getStateEventName(), $order, $context);

        $name = implode('.', [
            $event->getTransitionSide(),
            $event->getStateMachine()->getTechnicalName(),
            $orderTransaction->getPaymentMethod()->getShortName(),
            $event->getStateName(),
        ]);

        $this->dispatchEvent($name, $order, $context);
    }

    /**
     * @throws OrderNotFoundException
     */
    public function onOrderStateChange(StateMachineStateChangeEvent $event): void
    {
        $orderId = $event->getTransition()->getEntityId();

        $order = $this->getOrder($orderId, $event->getContext());

        if (!Feature::isActive('FEATURE_NEXT_9351')) {
            $this->dispatchEvent($event->getStateEventName(), $order, $event->getContext());

            return;
        }

        $context = $this->getContext($order, $event->getContext());

        $this->dispatchEvent($event->getStateEventName(), $order, $context);
    }

    public function onAddStateEvents(BusinessEventCollectorEvent $event): void
    {
        $context = $event->getContext();

        $collection = $event->getCollection();

        $payments = $this->paymentRepository->search(new Criteria(), $context);

        $criteria = new Criteria();
        $criteria->addAssociation('stateMachine');

        $states = $this->stateRepository->search($criteria, $context);

        $sides = [
            StateMachineStateChangeEvent::STATE_MACHINE_TRANSITION_SIDE_ENTER,
            StateMachineStateChangeEvent::STATE_MACHINE_TRANSITION_SIDE_LEAVE,
        ];

        /** @var StateMachineStateEntity $state */
        foreach ($states as $state) {
            foreach ($sides as $side) {
                $machine = $state->getStateMachine();
                if (!$machine) {
                    continue;
                }

                $name = implode('.', [
                    $side,
                    $machine->getTechnicalName(),
                    $state->getTechnicalName(),
                ]);

                $definition = $this->businessEventCollector->define(OrderStateMachineStateChangeEvent::class, $name);

                if (!$definition) {
                    continue;
                }

                $collection->set($name, $definition);

                if ($machine->getTechnicalName() !== OrderTransactionStates::STATE_MACHINE) {
                    continue;
                }

                /** @var PaymentMethodEntity $payment */
                foreach ($payments as $payment) {
                    $name = implode('.', [
                        $side,
                        $machine->getTechnicalName(),
                        $payment->getShortName(),
                        $state->getTechnicalName(),
                    ]);
                    $definition = $this->businessEventCollector->define(OrderStateMachineStateChangeEvent::class, $name);

                    if (!$definition) {
                        continue;
                    }

                    $collection->set($name, $definition);
                }
            }
        }
    }

    /**
     * @throws OrderNotFoundException
     */
    private function dispatchEvent(string $stateEventName, OrderEntity $order, Context $context): void
    {
        $this->eventDispatcher->dispatch(
            new OrderStateMachineStateChangeEvent($stateEventName, $order, $order->getSalesChannelId(), $context),
            $stateEventName
        );
    }

    private function getContext(OrderEntity $order, Context $context): Context
    {
        $context = clone $context;

        $salesChannelContext = $this->orderConverter->assembleSalesChannelContext($order, $context);

        if ($order->getRuleIds() !== null) {
            $salesChannelContext->setRuleIds($order->getRuleIds());
        }

        return $salesChannelContext->getContext();
    }

    /**
     * @throws OrderNotFoundException
     */
    private function getOrder(string $orderId, Context $context): OrderEntity
    {
        $orderCriteria = $this->getOrderCriteria($orderId);

        $order = $this->orderRepository
            ->search($orderCriteria, $context)
            ->first();

        if (!$order instanceof OrderEntity) {
            throw new OrderNotFoundException($orderId);
        }

        return $order;
    }

    private function getOrderCriteria(string $orderId): Criteria
    {
        $criteria = new Criteria([$orderId]);
        $criteria->addAssociation('orderCustomer.salutation');
        $criteria->addAssociation('stateMachineState');
        $criteria->addAssociation('transactions');
        $criteria->addAssociation('deliveries.shippingMethod');
        $criteria->addAssociation('salesChannel');

        $event = new OrderStateChangeCriteriaEvent($orderId, $criteria);
        $this->eventDispatcher->dispatch($event);

        return $criteria;
    }
}
