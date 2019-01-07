<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Rule;

use Shopware\Core\Framework\Rule\Collector\RuleConditionCollectorInterface;

class CustomerConditionCollector implements RuleConditionCollectorInterface
{
    public function getClasses(): array
    {
        return [
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
            ShippingZipCodeRule::class,
        ];
    }
}
