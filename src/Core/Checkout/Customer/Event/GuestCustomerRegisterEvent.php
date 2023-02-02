<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Event;

class GuestCustomerRegisterEvent extends CustomerRegisterEvent
{
    public const EVENT_NAME = 'checkout.customer.guest_register';

    public function getName(): string
    {
        return self::EVENT_NAME;
    }
}
