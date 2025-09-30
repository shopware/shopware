<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Listener;

use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopware\Core\Checkout\Order\Event\OrderStateChangeCriteriaEvent;
use Shopware\Core\Checkout\Order\Event\OrderStateMachineStateChangeEvent;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\OrderException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Event\BusinessEventCollector;
use Shopware\Core\Framework\Event\BusinessEventCollectorEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateCollection;
use Shopware\Core\System\StateMachine\Event\StateMachineStateChangeEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('checkout')]
class OrderStateChangeEventListener implements EventSubscriberInterface
{
    /**
     * @internal
     *
     * @param EntityRepository<OrderCollection> $orderRepository
     * @param EntityRepository<OrderTransactionCollection> $transactionRepository
     * @param EntityRepository<OrderDeliveryCollection> $deliveryRepository
     * @param EntityRepository<StateMachineStateCollection> $stateRepository
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

        $criteria = (new Criteria([$orderDeliveryId]))
            ->addAssociations(['order.orderCustomer', 'order.transactions.stateMachineState']);

        $orderDelivery = $this->deliveryRepository->search($criteria, $event->getContext())->getEntities()->first();
        if (!$orderDelivery || !$orderDelivery->getOrder()) {
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

        $criteria = (new Criteria([$orderTransactionId]))
            ->addAssociations([
                'paymentMethod',
                'order.orderCustomer',
                'order.transactions.stateMachineState',
            ]);

        $orderTransaction = $this->transactionRepository->search($criteria, $event->getContext())->getEntities()->first();
        if (!$orderTransaction || !$orderTransaction->getOrder() || !$orderTransaction->getPaymentMethod()) {
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

        $criteria = (new Criteria())
            ->addAssociation('stateMachine');

        $states = $this->stateRepository->search($criteria, $context)->getEntities();

        $sides = [
            StateMachineStateChangeEvent::STATE_MACHINE_TRANSITION_SIDE_ENTER,
            StateMachineStateChangeEvent::STATE_MACHINE_TRANSITION_SIDE_LEAVE,
        ];

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
        $order = $this->orderRepository->search(new Criteria([$orderId]), $context)->getEntities()->first();
        if (!$order) {
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
            $order->getTaxStatus() ?? $order->getPrice()->getTaxStatus(),
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
        $orderCriteria = $this->getOrderCriteria($orderId, $context);

        $order = $this->orderRepository->search($orderCriteria, $context)->getEntities()->first();
        if (!$order) {
            throw OrderException::orderNotFound($orderId);
        }

        return $order;
    }

    private function getOrderCriteria(string $orderId, Context $context): Criteria
    {
        $criteria = (new Criteria([$orderId]))
            ->addAssociations([
                'orderCustomer.salutation',
                'orderCustomer.customer',
                'stateMachineState',
                'deliveries.shippingMethod',
                'deliveries.shippingOrderAddress.country',
                'deliveries.shippingOrderAddress.countryState',
                'salesChannel',
                'language.locale',
                'transactions.paymentMethod',
                'lineItems',
                'lineItems.downloads.media',
                'currency',
                'addresses.country',
                'addresses.countryState',
                'tags',
            ]);

        $this->eventDispatcher->dispatch(new OrderStateChangeCriteriaEvent($orderId, $criteria, $context));

        return $criteria;
    }
}
