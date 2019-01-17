<?php declare(strict_types=1);

namespace Shopware\Storefront\Event;

use Shopware\Storefront\Pagelet\CartInfo\CartInfoPageletRequestEvent;
use Shopware\Storefront\Pagelet\CheckoutPaymentMethod\CheckoutPaymentMethodPageletRequestEvent;

class CheckoutEvents
{
    /**
     * Fired when a Cartinfo Pagelet request comes in and transformed to the CartInfoPageletRequest object
     *
     * @Event("CartInfoPageletRequestEvent")
     */
    public const CARTINFO_PAGELET_REQUEST = CartInfoPageletRequestEvent::NAME;

    /**
     * Fired when a Checkout Payment Method Pagelet request comes in and transformed to the CheckoutPaymentMethodPageletRequest object
     *
     * @Event("CheckoutPaymentMethodPageletRequestEvent")
     */
    public const CHECKOUTPAYMENTMETHOD_PAGELET_REQUEST = CheckoutPaymentMethodPageletRequestEvent::NAME;
}
