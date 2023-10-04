<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart;

use Shopware\Core\Framework\Event\BusinessEvents;
use Shopware\Core\Framework\Log\Package;

/**
 * @deprecated tag:v6.6.0 - will be removed without replacement
 */
#[Package('checkout')]
class CartEvents
{
    /**
     * @deprecated tag:v6.6.0 - use `Shopware\Core\Framework\Event\BusinessEvents::CHECKOUT_ORDER_PLACED` instead
     *
     * @Event("Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent")
     */
    final public const CHECKOUT_ORDER_PLACED = BusinessEvents::CHECKOUT_ORDER_PLACED;
}
