<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Subscriber;

use Shopware\Core\Checkout\Cart\Order\Transformer\LineItemTransformer;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\OrderEvents;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class OrderLoadedSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            OrderEvents::ORDER_LOADED_EVENT => 'addNestedLineItems',
        ];
    }

    public function addNestedLineItems(EntityLoadedEvent $event): void
    {
        /** @var OrderEntity $order */
        foreach ($event->getEntities() as $order) {
            if (!$order->getLineItems()) {
                continue;
            }

            $order->setNestedLineItems(LineItemTransformer::transformFlatToNested($order->getLineItems()));
        }
    }
}
