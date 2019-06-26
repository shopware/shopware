<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart;

class CartEvents
{
    /**
     * @Event("Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent")
     */
    public const CHECKOUT_ORDER_PLACED = 'checkout.order.placed';
}
