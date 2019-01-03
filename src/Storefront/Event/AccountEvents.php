<?php declare(strict_types=1);

namespace Shopware\Storefront\Event;

use Shopware\Storefront\Page\AccountAddress\AccountAddressPageLoadedEvent;
use Shopware\Storefront\Page\AccountAddress\AccountAddressPageRequestEvent;
use Shopware\Storefront\Page\AccountLogin\LoginPageRequestEvent;
use Shopware\Storefront\Page\AccountOrder\AccountOrderPageRequestEvent;
use Shopware\Storefront\Page\AccountOverview\AccountOverviewPageRequestEvent;
use Shopware\Storefront\Page\AccountPaymentMethod\AccountPaymentMethodPageRequestEvent;
use Shopware\Storefront\Page\AccountProfile\AccountProfilePageRequestEvent;
use Shopware\Storefront\Pagelet\AccountAddress\AddressPageletRequestEvent;
use Shopware\Storefront\Pagelet\AccountLogin\LoginPageletRequestEvent;
use Shopware\Storefront\Pagelet\AccountOrder\AccountOrderPageletRequestEvent;
use Shopware\Storefront\Pagelet\AccountPaymentMethod\AccountPaymentMethodPageletRequestEvent;
use Shopware\Storefront\Pagelet\AccountProfile\AccountProfilePageletRequestEvent;
use Shopware\Storefront\Pagelet\AccountRegistration\RegistrationPageletRequestEvent;

class AccountEvents
{
    /**
     * Dispatched as soon as the productpage has been loaded
     *
     * @Event("AccountPageLoadedEvent")
     */
    public const LOADED = AccountAddressPageLoadedEvent::NAME;

    /**
     * Fired when a AccountProfile page request comes in and transformed to the AccountOverviewPageRequest object
     *
     * @Event("AccountOverviewPageRequestEvent")
     */
    public const ACCOUNTOVERVIEW_PAGE_REQUEST = AccountOverviewPageRequestEvent::NAME;

    /**
     * Fired when a AccountAddress page request comes in and transformed to the AccountAddressPageRequest object
     *
     * @Event("AccountAddressPageRequestEvent")
     */
    public const ACCOUNTADDRESS_PAGE_REQUEST = AccountAddressPageRequestEvent::NAME;

    /**
     * Fired when a AccountPaymentMethod page request comes in and transformed to the AccountPaymentMethodPageRequest object
     *
     * @Event("AccountPaymentMethodPageRequestEvent")
     */
    public const ACCOUNT_PAYMENT_METHOD_PAGE_REQUEST = AccountPaymentMethodPageRequestEvent::NAME;

    /**
     * Fired when a AccountPaymentMethod pagelet request comes in and transformed to the AccountPaymentMethodPageletRequest object
     *
     * @Event("AccountPaymentMethodPageletRequestEvent")
     */
    public const ACCOUNT_PAYMENT_METHOD_PAGELET_REQUEST = AccountPaymentMethodPageletRequestEvent::NAME;

    /**
     * Fired when a AccountProfile page request comes in and transformed to the AccountProfilePageRequest object
     *
     * @Event("AccountOverviewPageRequestEvent")
     */
    public const ACCOUNTPROFILE_PAGE_REQUEST = AccountProfilePageRequestEvent::NAME;

    /**
     * Fired when a AccountProfile pagelet request comes in and transformed to the AccountProfilePageletRequest object
     *
     * @Event("AccountProfilePageletRequestEvent")
     */
    public const ACCOUNTPROFILE_PAGELET_REQUEST = AccountProfilePageletRequestEvent::NAME;

    /**
     * Fired when a AccountOrder page request comes in and transformed to the AccountOrderPageRequest object
     *
     * @Event("AccountOrderPageRequestEvent")
     */
    public const ACCOUNTORDER_PAGE_REQUEST = AccountOrderPageRequestEvent::NAME;

    /**
     * Fired when a AccountOrder pagelet request comes in and transformed to the AccountOrderPageletRequest object
     *
     * @Event("AccountOrderPageletRequestEvent")
     */
    public const ACCOUNTORDER_PAGELET_REQUEST = AccountOrderPageletRequestEvent::NAME;

    /**
     * Fired when a Address pagelet request comes in and transformed to the AddressPageletRequest object
     *
     * @Event("DetailPageletRequestEvent")
     */
    public const ADDRESS_PAGELET_REQUEST = AddressPageletRequestEvent::NAME;

    /**
     * Fired when a Login page request comes in and transformed to the LoginPageRequest object
     *
     * @Event("LoginPageRequestEvent")
     */
    public const LOGIN_PAGE_REQUEST = LoginPageRequestEvent::NAME;

    /**
     * Fired when a login pagelet request comes in and transformed to the LoginPageletRequest object
     *
     * @Event("LoginPageletRequestEvent")
     */
    public const LOGIN_PAGELET_REQUEST = LoginPageletRequestEvent::NAME;

    /**
     * Fired when a registration pagelet request comes in and transformed to the RegistrationPageletRequest object
     *
     * @Event("RegistrationPageletRequestEvent")
     */
    public const REGISTRATION_PAGELET_REQUEST = RegistrationPageletRequestEvent::NAME;
}
