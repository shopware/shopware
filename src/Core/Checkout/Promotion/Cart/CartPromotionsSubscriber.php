<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Cart;

use Shopware\Core\Checkout\Cart\Event\BeforeCartMergeEvent;
use Shopware\Core\Checkout\Promotion\Cart\Extension\CartExtension;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('checkout')]
class CartPromotionsSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [BeforeCartMergeEvent::class => 'onBeforeCartMerge'];
    }

    public function onBeforeCartMerge(BeforeCartMergeEvent $event): void
    {
        $guestCart = $event->getGuestCart();
        $customerCart = $event->getCustomerCart();

        if (!$guestCart->hasExtension(CartExtension::KEY)) {
            return;
        }

        $guestPromotions = $guestCart->getExtensionOfType(CartExtension::KEY, CartExtension::class) ?? new CartExtension();
        $customerPromotions = $customerCart->getExtensionOfType(CartExtension::KEY, CartExtension::class) ?? new CartExtension();

        $customerCart->addExtension(CartExtension::KEY, $customerPromotions->merge($guestPromotions));
    }
}
