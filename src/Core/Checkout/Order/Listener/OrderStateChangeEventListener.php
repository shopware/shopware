<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Listener;

use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Shopware\Core\Checkout\Order\Event\OrderStateMachineStateChangeEvent;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\StateMachine\Event\StateMachineStateChangeEvent;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class OrderStateChangeEventListener
{
    /**
     * @var EntityRepositoryInterface
     */
    private $orderRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $orderTransactionRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $orderDeliveryRepository;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    public function __construct(
        EntityRepositoryInterface $orderRepository,
        EntityRepositoryInterface $orderTransactionRepository,
        EntityRepositoryInterface $orderDeliveryRepository,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->orderRepository = $orderRepository;
        $this->orderTransactionRepository = $orderTransactionRepository;
        $this->orderDeliveryRepository = $orderDeliveryRepository;
        $this->eventDispatcher = $eventDispatcher;
    }

    public function onOrderDeliveryStateChange(StateMachineStateChangeEvent $event): void
    {
        /** @var OrderDeliveryEntity $orderDelivery */
        $orderDelivery = $this->orderDeliveryRepository->search(
            new Criteria([$event->getTransition()->getEntityId()]),
            $event->getContext()
        )->first();
        $orderCriteria = $this->getOrderCriteria($orderDelivery->getOrderId());
        /** @var OrderEntity $order */
        $order = $this->orderRepository->search($orderCriteria, $event->getContext())->first();

        $this->eventDispatcher->dispatch(
            new OrderStateMachineStateChangeEvent($event->getStateEventName(), $order, $order->getSalesChannelId(), $event->getContext()),
            $event->getStateEventName()
        );
    }

    public function onOrderTransactionStateChange(StateMachineStateChangeEvent $event): void
    {
        /** @var OrderDeliveryEntity $orderDelivery */
        $orderDelivery = $this->orderTransactionRepository->search(
            new Criteria([$event->getTransition()->getEntityId()]),
            $event->getContext()
        )->first();
        $orderCriteria = $this->getOrderCriteria($orderDelivery->getOrderId());
        /** @var OrderEntity $order */
        $order = $this->orderRepository->search($orderCriteria, $event->getContext())->first();

        $this->eventDispatcher->dispatch(
            new OrderStateMachineStateChangeEvent($event->getStateEventName(), $order, $order->getSalesChannelId(), $event->getContext()),
            $event->getStateEventName()
        );
    }

    public function onOrderStateChange(StateMachineStateChangeEvent $event): void
    {
        $orderCriteria = $this->getOrderCriteria($event->getTransition()->getEntityId());
        /** @var OrderEntity $order */
        $order = $this->orderRepository->search($orderCriteria, $event->getContext())->first();

        $this->eventDispatcher->dispatch(
            new OrderStateMachineStateChangeEvent($event->getStateEventName(), $order, $order->getSalesChannelId(), $event->getContext()),
            $event->getStateEventName()
        );
    }

    private function getOrderCriteria(string $orderId): Criteria
    {
        $orderCriteria = new Criteria([$orderId]);
        $orderCriteria->addAssociation('orderCustomer.salutation');
        $orderCriteria->addAssociation('stateMachineState');
        $orderCriteria->addAssociation('transactions');
        $orderCriteria->addAssociation('salesChannel');

        return $orderCriteria;
    }
}
