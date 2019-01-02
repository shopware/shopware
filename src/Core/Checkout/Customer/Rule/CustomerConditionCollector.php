<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Rule;

use Shopware\Core\Framework\Rule\Collector\CollectConditionEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class CustomerConditionCollector implements EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return [
            CollectConditionEvent::NAME => 'collectConditions',
        ];
    }

    public function collectConditions(CollectConditionEvent $collectConditionEvent): void
    {
        $collectConditionEvent->addClasses(
            BillingCountryRule::class,
            BillingStreetRule::class,
            BillingZipCodeRule::class,
            CustomerGroupRule::class,
            CustomerNumberRule::class,
            DifferentAddressesRule::class,
            IsNewCustomerRule::class,
            LastNameRule::class,
            ShippingCountryRule::class,
            ShippingStreetRule::class,
            ShippingZipCodeRule::class
        );
    }
}
