<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Event;

use Shopware\Core\Framework\Event\FlowEventAware;
use Shopware\Core\Framework\Log\Package;

#[Package('customer-order')]
class GuestCustomerRegisterEvent extends CustomerRegisterEvent implements FlowEventAware
{
    final public const EVENT_NAME = 'checkout.customer.guest_register';

    public function getName(): string
    {
        return self::EVENT_NAME;
    }
}
