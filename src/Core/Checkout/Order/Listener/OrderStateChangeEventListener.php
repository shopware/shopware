<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Listener;

use Shopware\Core\Checkout\Cart\Exception\OrderDeliveryNotFoundException;
use Shopware\Core\Checkout\Cart\Exception\OrderNotFoundException;
use Shopware\Core\Checkout\Cart\Exception\OrderTransactionNotFoundException;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Shopware\Core\Checkout\Order\Event\OrderStateMachineStateChangeEvent;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\StateMachine\Event\StateMachineStateChangeEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class OrderStateChangeEventListener implements EventSubscriberInterface
{
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

    public function __construct(
        EntityRepositoryInterface $orderRepository,
        EntityRepositoryInterface $transactionRepository,
        EntityRepositoryInterface $deliveryRepository,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->orderRepository = $orderRepository;
        $this->transactionRepository = $transactionRepository;
        $this->deliveryRepository = $deliveryRepository;
        $this->eventDispatcher = $eventDispatcher;
    }

    public static function getSubscribedEvents()
    {
        return [
            'state_machine.order.state_changed' => 'onOrderStateChange',
            'state_machine.order_delivery.state_changed' => 'onOrderDeliveryStateChange',
            'state_machine.order_transaction.state_changed' => 'onOrderTransactionStateChange',
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

        $orderId = $orderDelivery->getOrderId();

        $this->dispatchEvent($event->getStateEventName(), $orderId, $context);
    }

    /**
     * @throws OrderNotFoundException
     * @throws OrderTransactionNotFoundException
     */
    public function onOrderTransactionStateChange(StateMachineStateChangeEvent $event): void
    {
        $orderTransactionId = $event->getTransition()->getEntityId();
        $context = $event->getContext();

        $criteria = new Criteria([$orderTransactionId]);
        $orderTransaction = $this->transactionRepository
            ->search($criteria, $context)
            ->first();

        if ($orderTransaction === null) {
            throw new OrderTransactionNotFoundException($orderTransactionId);
        }

        $orderId = $orderTransaction->getOrderId();

        $this->dispatchEvent($event->getStateEventName(), $orderId, $context);
    }

    /**
     * @throws OrderNotFoundException
     */
    public function onOrderStateChange(StateMachineStateChangeEvent $event): void
    {
        $orderId = $event->getTransition()->getEntityId();

        $this->dispatchEvent($event->getStateEventName(), $orderId, $event->getContext());
    }

    /**
     * @throws OrderNotFoundException
     */
    private function dispatchEvent(string $stateEventName, string $orderId, Context $context): void
    {
        $order = $this->getOrder($orderId, $context);

        $this->eventDispatcher->dispatch(
            new OrderStateMachineStateChangeEvent(
                $stateEventName,
                $order,
                $order->getSalesChannelId(),
                $context
            ),
            $stateEventName
        );
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

        return $criteria;
    }
}
