<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart;

use Shopware\Core\Framework\Log\Package;
/**
 * @package checkout
 */
#[Package('checkout')]
class CartEvents
{
    /**
     * @Event("Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent")
     */
    public const CHECKOUT_ORDER_PLACED = 'checkout.order.placed';
}
