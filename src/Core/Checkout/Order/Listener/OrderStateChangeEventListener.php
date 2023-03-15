<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Listener;

use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Shopware\Core\Checkout\Order\Event\OrderStateChangeCriteriaEvent;
use Shopware\Core\Checkout\Order\Event\OrderStateMachineStateChangeEvent;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\OrderException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Event\BusinessEventCollector;
use Shopware\Core\Framework\Event\BusinessEventCollectorEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;
use Shopware\Core\System\StateMachine\Event\StateMachineStateChangeEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('customer-order')]
class OrderStateChangeEventListener implements EventSubscriberInterface
{
    /**
     * @internal
     */
    public function __construct(
        private readonly EntityRepository $orderRepository,
        private readonly EntityRepository $transactionRepository,
        private readonly EntityRepository $deliveryRepository,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly BusinessEventCollector $businessEventCollector,
        private readonly EntityRepository $stateRepository
    ) {
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
     * @throws OrderException
     */
    public function onOrderDeliveryStateChange(StateMachineStateChangeEvent $event): void
    {
        $orderDeliveryId = $event->getTransition()->getEntityId();

        $criteria = new Criteria([$orderDeliveryId]);
        $criteria->addAssociation('order.orderCustomer');
        $criteria->addAssociation('order.transactions.stateMachineState');

        /** @var OrderDeliveryEntity|null $orderDelivery */
        $orderDelivery = $this->deliveryRepository
            ->search($criteria, $event->getContext())
            ->first();

        if ($orderDelivery === null) {
            throw OrderException::orderDeliveryNotFound($orderDeliveryId);
        }

        if ($orderDelivery->getOrder() === null) {
            throw OrderException::orderDeliveryNotFound($orderDeliveryId);
        }

        $context = $this->getContext($orderDelivery->getOrderId(), $event->getContext());
        $order = $this->getOrder($orderDelivery->getOrderId(), $context);

        $this->dispatchEvent($event->getStateEventName(), $order, $context);
    }

    /**
     * @throws OrderException
     */
    public function onOrderTransactionStateChange(StateMachineStateChangeEvent $event): void
    {
        $orderTransactionId = $event->getTransition()->getEntityId();

        $criteria = new Criteria([$orderTransactionId]);
        $criteria->addAssociation('paymentMethod');
        $criteria->addAssociation('order.orderCustomer');
        $criteria->addAssociation('order.transactions.stateMachineState');

        $orderTransaction = $this->transactionRepository
            ->search($criteria, $event->getContext())
            ->first();

        if ($orderTransaction === null) {
            throw OrderException::orderTransactionNotFound($orderTransactionId);
        }

        if ($orderTransaction->getPaymentMethod() === null) {
            throw OrderException::orderTransactionNotFound($orderTransactionId);
        }

        if ($orderTransaction->getOrder() === null) {
            throw OrderException::orderTransactionNotFound($orderTransactionId);
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
     * @throws OrderException
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
            throw OrderException::orderNotFound($orderId);
        }

        /** @var CashRoundingConfig $itemRounding */
        $itemRounding = $order->getItemRounding();

        $orderContext = new Context(
            $context->getSource(),
            $order->getRuleIds() ?? [],
            $order->getCurrencyId(),
            array_values(array_unique(array_merge([$order->getLanguageId()], $context->getLanguageIdChain()))),
            $context->getVersionId(),
            $order->getCurrencyFactor(),
            true,
            $order->getTaxStatus(),
            $itemRounding
        );

        $orderContext->addState(...$context->getStates());
        $orderContext->addExtensions($context->getExtensions());

        return $orderContext;
    }

    /**
     * @throws OrderException
     */
    private function getOrder(string $orderId, Context $context): OrderEntity
    {
        $orderCriteria = $this->getOrderCriteria($orderId);

        $order = $this->orderRepository
            ->search($orderCriteria, $context)
            ->first();

        if (!$order instanceof OrderEntity) {
            throw OrderException::orderNotFound($orderId);
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
        $criteria->addAssociation('lineItems.downloads.media');
        $criteria->addAssociation('currency');
        $criteria->addAssociation('addresses.country');
        $criteria->addAssociation('addresses.countryState');
        $criteria->addAssociation('tags');

        $event = new OrderStateChangeCriteriaEvent($orderId, $criteria);
        $this->eventDispatcher->dispatch($event);

        return $criteria;
    }
}
