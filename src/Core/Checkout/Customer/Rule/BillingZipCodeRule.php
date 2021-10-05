<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Rule;

use Shopware\Core\Checkout\CheckoutRuleScope;
use Shopware\Core\Framework\Rule\Container\ZipCodeRule;
use Shopware\Core\Framework\Rule\RuleScope;

class BillingZipCodeRule extends ZipCodeRule
{
    public function match(RuleScope $scope): bool
    {
        if (!$scope instanceof CheckoutRuleScope) {
            return false;
        }

        if (!$customer = $scope->getSalesChannelContext()->getCustomer()) {
            return false;
        }

        if (!$address = $customer->getActiveBillingAddress()) {
            return false;
        }

        return $this->matchZipCode($address);
    }

    public function getName(): string
    {
        return 'customerBillingZipCode';
    }
}
