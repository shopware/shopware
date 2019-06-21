<?php declare(strict_types=1);

namespace Shopware\Storefront\Event;

use Shopware\Storefront\Page\Checkout\Cart\CheckoutCartPageLoadedEvent;
use Shopware\Storefront\Page\Checkout\Confirm\CheckoutConfirmPageLoadedEvent;
use Shopware\Storefront\Page\Checkout\Finish\CheckoutFinishPageLoadedEvent;
use Shopware\Storefront\Page\Checkout\Offcanvas\OffcanvasCartPageLoadedEvent;
use Shopware\Storefront\Page\Checkout\Register\CheckoutRegisterPageLoadedEvent;

class CheckoutEvents
{
    /**
     * @Event("Shopware\Storefront\Page\Checkout\Cart\CheckoutCartPageLoadedEvent")
     */
    public const CHECKOUT_CART_PAGE_LOADED_EVENT = CheckoutCartPageLoadedEvent::NAME;

    /**
     * @Event("Shopware\Storefront\Page\Checkout\Confirm\CheckoutConfirmPageLoadedEvent")
     */
    public const CHECKOUT_CONFIRM_PAGE_LOADED_EVENT = CheckoutConfirmPageLoadedEvent::NAME;

    /**
     * @Event("Shopware\Storefront\Page\Checkout\Finish\CheckoutFinishPageLoadedEvent")
     */
    public const CHECKOUT_FINISH_PAGE_LOADED_EVENT = CheckoutFinishPageLoadedEvent::NAME;

    /**
     * @Event("\Shopware\Storefront\Page\Checkout\Offcanvas\OffcanvasCartPageLoadedEvent")
     */
    public const CHECKOUT_OFFCANVAS_CART_PAGE_LOADED_EVENT = OffcanvasCartPageLoadedEvent::NAME;

    /**
     * @Event("Shopware\Storefront\Page\Checkout\Cart\CheckoutRegisterPageLoadedEvent")
     */
    public const CHECKOUT_REGISTER_PAGE_LOADED_EVENT = CheckoutRegisterPageLoadedEvent::NAME;
}
