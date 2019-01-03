<?php declare(strict_types=1);

namespace Shopware\Storefront\Event;

use Shopware\Storefront\Pagelet\CartInfo\CartInfoPageletRequestEvent;

class CheckoutEvents
{
    /**
     * Fired when a Cartinfo Pagelet request comes in and transformed to the CartInfoPageletRequest object
     *
     * @Event("Shopware\Storefront\Checkout\Event\CartInfoPageletRequestEvent")
     */
    public const CARTINFO_PAGELET_REQUEST = CartInfoPageletRequestEvent::NAME;
}
