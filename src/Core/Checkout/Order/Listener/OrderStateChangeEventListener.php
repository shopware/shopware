<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Listener;

use Shopware\Core\Checkout\Cart\Exception\OrderDeliveryNotFoundException;
use Shopware\Core\Checkout\Cart\Exception\OrderNotFoundException;
use Shopware\Core\Checkout\Cart\Exception\OrderTransactionNotFoundException;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Shopware\Core\Checkout\Order\Event\OrderStateChangeCriteriaEvent;
use Shopware\Core\Checkout\Order\Event\OrderStateMachineStateChangeEvent;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\OrderException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Event\BusinessEventCollector;
use Shopware\Core\Framework\Event\BusinessEventCollectorEvent;
use Shopware\Core\Framework\Feature;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;
use Shopware\Core\System\StateMachine\Event\StateMachineStateChangeEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @deprecated tag:v6.5.0 - reason:becomes-internal - EventSubscribers will become internal in v6.5.0
 */
class OrderStateChangeEventListener implements EventSubscriberInterface
{
    private EntityRepository $stateRepository;

    private EntityRepository $orderRepository;

    private EntityRepository $transactionRepository;

    private EntityRepository $deliveryRepository;

    private EventDispatcherInterface $eventDispatcher;

    private BusinessEventCollector $businessEventCollector;

    /**
     * @internal
     */
    public function __construct(
        EntityRepository $orderRepository,
        EntityRepository $transactionRepository,
        EntityRepository $deliveryRepository,
        EventDispatcherInterface $eventDispatcher,
        BusinessEventCollector $businessEventCollector,
        EntityRepository $stateRepository
    ) {
        $this->orderRepository = $orderRepository;
        $this->transactionRepository = $transactionRepository;
        $this->deliveryRepository = $deliveryRepository;
        $this->eventDispatcher = $eventDispatcher;
        $this->stateRepository = $stateRepository;
        $this->businessEventCollector = $businessEventCollector;
    }

    public static function getSubscribedEvents(): array
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

        $criteria = new Criteria([$orderDeliveryId]);
        $criteria->addAssociation('order.orderCustomer');
        $criteria->addAssociation('order.transactions');

        /** @var OrderDeliveryEntity|null $orderDelivery */
        $orderDelivery = $this->deliveryRepository
            ->search($criteria, $event->getContext())
            ->first();

        if ($orderDelivery === null) {
            if (Feature::isActive('v6.5.0.0')) {
                throw OrderException::orderDeliveryNotFound($orderDeliveryId);
            }

            throw new OrderDeliveryNotFoundException($orderDeliveryId);
        }

        if ($orderDelivery->getOrder() === null) {
            if (Feature::isActive('v6.5.0.0')) {
                throw OrderException::orderDeliveryNotFound($orderDeliveryId);
            }

            throw new OrderNotFoundException($orderDeliveryId);
        }

        $context = $this->getContext($orderDelivery->getOrderId(), $event->getContext());
        $order = $this->getOrder($orderDelivery->getOrderId(), $context);

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
        $criteria->addAssociation('order.orderCustomer');
        $criteria->addAssociation('order.transactions');

        $orderTransaction = $this->transactionRepository
            ->search($criteria, $event->getContext())
            ->first();

        if ($orderTransaction === null) {
            if (Feature::isActive('v6.5.0.0')) {
                throw OrderException::orderTransactionNotFound($orderTransactionId);
            }

            throw new OrderTransactionNotFoundException($orderTransactionId);
        }

        if ($orderTransaction->getPaymentMethod() === null) {
            if (Feature::isActive('v6.5.0.0')) {
                throw OrderException::orderTransactionNotFound($orderTransactionId);
            }

            throw new OrderTransactionNotFoundException($orderTransactionId);
        }

        if ($orderTransaction->getOrder() === null) {
            if (Feature::isActive('v6.5.0.0')) {
                throw OrderException::orderTransactionNotFound($orderTransactionId);
            }

            throw new OrderNotFoundException($orderTransactionId);
        }

        $context = $this->getContext($orderTransaction->getOrderId(), $event->getContext());
        $order = $this->getOrder($orderTransaction->getOrderId(), $context);

        $this->dispatchEvent($event->getStateEventName(), $order, $context);
    }

    public function onOrderStateChange(StateMachineStateChangeEvent $event): void
    {
        $orderId = $event->getTransition()->getEntityId();

        $context = $this->getContext($orderId, $event->getContext());
        $order = $this->getOrder($orderId, $context);

        $this->dispatchEvent($event->getStateEventName(), $order, $context);
    }

    public function onAddStateEvents(BusinessEventCollectorEvent $event): void
    {
        $context = $event->getContext();

        $collection = $event->getCollection();

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
            }
        }
    }

    /**
     * @throws OrderNotFoundException
     */
    private function dispatchEvent(string $stateEventName, OrderEntity $order, Context $context): void
    {
        $this->eventDispatcher->dispatch(
            new OrderStateMachineStateChangeEvent($stateEventName, $order, $context),
            $stateEventName
        );
    }

    private function getContext(string $orderId, Context $context): Context
    {
        $order = $this->orderRepository->search(new Criteria([$orderId]), $context)->first();

        if (!$order instanceof OrderEntity) {
            throw new OrderNotFoundException($orderId);
        }

        $orderContext = new Context(
            $context->getSource(),
            $order->getRuleIds() ?? [],
            $order->getCurrencyId(),
            array_values(array_unique(array_merge([$order->getLanguageId()], $context->getLanguageIdChain()))),
            $context->getVersionId(),
            $order->getCurrencyFactor(),
            true,
            $order->getTaxStatus(),
            $order->getItemRounding()
        );

        $orderContext->addState(...$context->getStates());
        $orderContext->addExtensions($context->getExtensions());

        return $orderContext;
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
        $criteria->addAssociation('orderCustomer.customer');
        $criteria->addAssociation('stateMachineState');
        $criteria->addAssociation('deliveries.shippingMethod');
        $criteria->addAssociation('deliveries.shippingOrderAddress.country');
        $criteria->addAssociation('deliveries.shippingOrderAddress.countryState');
        $criteria->addAssociation('salesChannel');
        $criteria->addAssociation('language.locale');
        $criteria->addAssociation('transactions.paymentMethod');
        $criteria->addAssociation('lineItems');
        $criteria->addAssociation('currency');
        $criteria->addAssociation('addresses.country');
        $criteria->addAssociation('addresses.countryState');
        $criteria->addAssociation('tags');

        $event = new OrderStateChangeCriteriaEvent($orderId, $criteria);
        $this->eventDispatcher->dispatch($event);

        return $criteria;
    }
}
