<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Listener;

use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\OrderEvents;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class OrderLoadedEventListener implements EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return [
            OrderEvents::ORDER_LOADED_EVENT => 'orderLoaded',
        ];
    }

    public function orderLoaded(EntityLoadedEvent $event): void
    {
        /** @var OrderEntity $order */
        foreach ($event->getEntities() as $order) {
            if ($order->getLineItems() instanceof OrderLineItemCollection) {
                $order->getLineItems()->sortByPosition();
            }
        }
    }
}
