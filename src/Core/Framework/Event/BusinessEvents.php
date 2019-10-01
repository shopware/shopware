<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Event;

use Shopware\Core\Checkout\Customer\Event\CustomerBeforeLoginEvent;
use Shopware\Core\Checkout\Customer\Event\CustomerChangedPaymentMethodEvent;
use Shopware\Core\Checkout\Customer\Event\CustomerLoginEvent;
use Shopware\Core\Checkout\Customer\Event\CustomerLogoutEvent;
use Shopware\Core\System\User\Recovery\UserRecoveryRequestEvent;

final class BusinessEvents
{
    /**
     * @Event("Shopware\Core\Framework\Event\BusinessEvent")
     */
    public const GLOBAL_EVENT = 'shopware.global_business_event';

    /**
     * @Event("Shopware\Core\Checkout\Customer\Event\CustomerBeforeLoginEvent")
     */
    public const CHECKOUT_CUSTOMER_BEFORE_LOGIN = CustomerBeforeLoginEvent::EVENT_NAME;

    /**
     * @Event("Shopware\Core\Checkout\Customer\Event\CustomerLoginEvent")
     */
    public const CHECKOUT_CUSTOMER_LOGIN = CustomerLoginEvent::EVENT_NAME;

    /**
     * @Event("Shopware\Core\Checkout\Customer\Event\CustomerLogoutEvent")
     */
    public const CHECKOUT_CUSTOMER_LOGOUT = CustomerLogoutEvent::EVENT_NAME;

    /**
     * @Event("Shopware\Core\System\User\Recovery\UserRecoveryRequestEvent")
     */
    public const USER_RECOVERY_REQUEST = UserRecoveryRequestEvent::EVENT_NAME;

    /**
     * @Event("Shopware\Core\Checkout\Customer\Event\CustomerChangedPaymentMethodEvent")
     */
    public const CHECKOUT_CUSTOMER_CHANGED_PAYMENT_METHOD = CustomerChangedPaymentMethodEvent::EVENT_NAME;

    private function __construct()
    {
    }
}
