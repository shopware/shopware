<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Delivery;

use Shopware\Core\Checkout\Cart\Delivery\Struct\SelectedDeliveryShippingMethods;
use Shopware\Core\Checkout\Cart\Event\BeforeCartMergeEvent;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('checkout')]
class SelectedDeliveryShippingMethodsSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [BeforeCartMergeEvent::class => 'onBeforeCartMerge'];
    }

    public function onBeforeCartMerge(BeforeCartMergeEvent $event): void
    {
        $guestSelection = $event->getGuestCart()->getExtensionOfType(SelectedDeliveryShippingMethods::KEY, SelectedDeliveryShippingMethods::class);
        if ($guestSelection === null) {
            return;
        }

        $customerCart = $event->getCustomerCart();
        $customerSelection = $customerCart->getExtensionOfType(SelectedDeliveryShippingMethods::KEY, SelectedDeliveryShippingMethods::class)
            ?? new SelectedDeliveryShippingMethods();

        $customerCart->addExtension(SelectedDeliveryShippingMethods::KEY, $customerSelection->merge($guestSelection));
    }
}
