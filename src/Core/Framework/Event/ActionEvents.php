<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Event;

use Shopware\Core\Checkout\Customer\Event\CustomerLoginEvent;
use Shopware\Core\Checkout\Customer\Event\CustomerLogoutEvent;

final class ActionEvents
{
    /**
     * @Event("Shopware\Core\Checkout\Customer\Event\CustomerLoginEvent")
     */
    public const CHECKOUT_CUSTOMER_LOGIN = CustomerLoginEvent::EVENT_NAME;

    /**
     * @Event("Shopware\Core\Checkout\Customer\Event\CustomerLogoutEvent")
     */
    public const CHECKOUT_CUSTOMER_LOGOUT = CustomerLogoutEvent::EVENT_NAME;
}
