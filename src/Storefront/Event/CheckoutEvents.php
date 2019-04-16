<?php declare(strict_types=1);

namespace Shopware\Storefront\Event;

use Shopware\Storefront\Page\Checkout\Address\CheckoutAddressPageLoadedEvent;
use Shopware\Storefront\Page\Checkout\AddressList\CheckoutAddressListPageLoadedEvent;
use Shopware\Storefront\Page\Checkout\Cart\CheckoutCartPageLoadedEvent;
use Shopware\Storefront\Page\Checkout\Confirm\CheckoutConfirmPageLoadedEvent;
use Shopware\Storefront\Page\Checkout\Finish\CheckoutFinishPageLoadedEvent;
use Shopware\Storefront\Page\Checkout\Register\CheckoutRegisterPageLoadedEvent;
use Shopware\Storefront\Pagelet\Checkout\AjaxCart\CheckoutAjaxCartPageletLoadedEvent;
use Shopware\Storefront\Pagelet\Checkout\Info\CheckoutInfoPageletLoadedEvent;

class CheckoutEvents
{
    /**
     * @Event("Shopware\Storefront\Pagelet\Checkout\AjaxCart\CheckoutAjaxCartPageletLoadedEvent")
     */
    public const CHECKOUT_AJAXCART_PAGELET_LOADED_EVENT = CheckoutAjaxCartPageletLoadedEvent::NAME;

    /**
     * @Event("Shopware\Storefront\Pagelet\Checkout\Info\CheckoutInfoPageletLoadedEvent")
     */
    public const CHECKOUT_INFO_PAGELET_LOADED_EVENT = CheckoutInfoPageletLoadedEvent::NAME;

    /**
     * @Event("Shopware\Storefront\Page\Checkout\Finish\CheckoutFinishPageLoadedEvent")
     */
    public const CHECKOUT_FINISH_PAGE_LOADED_EVENT = CheckoutFinishPageLoadedEvent::NAME;

    /**
     * @Event("Shopware\Storefront\Page\Checkout\Confirm\CheckoutConfirmPageLoadedEvent")
     */
    public const CHECKOUT_CONFIRM_PAGE_LOADED_EVENT = CheckoutConfirmPageLoadedEvent::NAME;

    /**
     * @Event("Shopware\Storefront\Page\Checkout\Cart\CheckoutCartPageLoadedEvent")
     */
    public const CHECKOUT_CART_PAGE_LOADED_EVENT = CheckoutCartPageLoadedEvent::NAME;

    /**
     * @Event("Shopware\Storefront\Page\Checkout\Cart\CheckoutRegisterPageLoadedEvent")
     */
    public const CHECKOUT_REGISTER_PAGE_LOADED_EVENT = CheckoutRegisterPageLoadedEvent::NAME;

    /**
     * @Event("Shopware\Storefront\Page\Checkout\Address\CheckoutAddressPageLoadedEvent")
     */
    public const CHECKOUT_ADDRESS_PAGE_LOADED_EVENT = CheckoutAddressPageLoadedEvent::NAME;

    /**
     * @Event("Shopware\Storefront\Page\Checkout\AddressList\CheckoutAddressListPageLoadedEvent")
     */
    public const CHECKOUT_ADDRESS_LIST_PAGE_LOADED_EVENT = CheckoutAddressListPageLoadedEvent::NAME;
}
