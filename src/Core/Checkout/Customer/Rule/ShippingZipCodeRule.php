<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Rule;

use Shopware\Core\Checkout\CheckoutRuleScope;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Container\ZipCodeRule;
use Shopware\Core\Framework\Rule\RuleComparison;
use Shopware\Core\Framework\Rule\RuleScope;

/**
 * @deprecated tag:v6.7.0 - reason:becomes-internal - Will be internal in v6.7.0
 */
#[Package('fundamentals@after-sales')]
class ShippingZipCodeRule extends ZipCodeRule
{
    final public const RULE_NAME = 'customerShippingZipCode';

    public function match(RuleScope $scope): bool
    {
        if (!$scope instanceof CheckoutRuleScope) {
            return false;
        }

        if (!$address = $scope->getSalesChannelContext()->getShippingLocation()->getAddress()) {
            return RuleComparison::isNegativeOperator($this->operator);
        }

        return $this->matchZipCode($address);
    }
}
