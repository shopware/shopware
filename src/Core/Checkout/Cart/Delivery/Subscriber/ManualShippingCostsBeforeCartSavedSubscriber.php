<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Delivery\Subscriber;

use Shopware\Core\Checkout\Cart\Delivery\DeliveryProcessor;
use Shopware\Core\Checkout\Cart\Event\BeforeCartSavedEvent;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ManualShippingCostsBeforeCartSavedSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            BeforeCartSavedEvent::class => 'needsSaving',
        ];
    }

    public function needsSaving(BeforeCartSavedEvent $event): void
    {
        $extension = $event->getCart()->getExtension(DeliveryProcessor::MANUAL_SHIPPING_COSTS);
        if (!$extension instanceof CalculatedPrice) {
            return;
        }

        $event->needsSaving();
    }
}
