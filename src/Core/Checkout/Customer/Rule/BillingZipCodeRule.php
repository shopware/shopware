<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Rule;

use Shopware\Core\Checkout\CheckoutRuleScope;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Container\ZipCodeRule;
use Shopware\Core\Framework\Rule\RuleComparison;
use Shopware\Core\Framework\Rule\RuleScope;

#[Package('services-settings')]
class BillingZipCodeRule extends ZipCodeRule
{
    final public const RULE_NAME = 'customerBillingZipCode';

    public function match(RuleScope $scope): bool
    {
        if (!$scope instanceof CheckoutRuleScope) {
            return false;
        }

        if (!$customer = $scope->getSalesChannelContext()->getCustomer()) {
            return RuleComparison::isNegativeOperator($this->operator);
        }

        if (!$address = $customer->getActiveBillingAddress()) {
            return RuleComparison::isNegativeOperator($this->operator);
        }

        return $this->matchZipCode($address);
    }
}
