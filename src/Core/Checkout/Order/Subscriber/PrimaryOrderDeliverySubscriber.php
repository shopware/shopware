<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Subscriber;

use Shopware\Core\Checkout\Order\OrderEvents;
use Shopware\Core\Checkout\Order\PrimaryOrderDeliveryService;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('core')]
class PrimaryOrderDeliverySubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly PrimaryOrderDeliveryService $primaryOrderDeliveryService,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            OrderEvents::ORDER_DELIVERY_WRITTEN_EVENT => 'updatePrimaryOrderDelivery',
        ];
    }

    public function updatePrimaryOrderDelivery(EntityWrittenEvent $event): void
    {
        $orderIds = [];
        foreach ($event->getPayloads() as $payload) {
            if (!isset($payload['orderId'])) {
                continue;
            }

            $orderIds[] = $payload['orderId'];
        }

        $this->primaryOrderDeliveryService->recalculatePrimaryOrderDeliveries($orderIds);
    }
}
