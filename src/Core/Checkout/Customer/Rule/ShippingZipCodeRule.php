<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Rule;

use Shopware\Core\Checkout\CheckoutRuleScope;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Rule\Container\ZipCodeRule;
use Shopware\Core\Framework\Rule\RuleComparison;
use Shopware\Core\Framework\Rule\RuleScope;

/**
 * @package business-ops
 */
class ShippingZipCodeRule extends ZipCodeRule
{
    public function match(RuleScope $scope): bool
    {
        if (!$scope instanceof CheckoutRuleScope) {
            return false;
        }

        if (!$address = $scope->getSalesChannelContext()->getShippingLocation()->getAddress()) {
            if (!Feature::isActive('v6.5.0.0')) {
                return false;
            }

            return RuleComparison::isNegativeOperator($this->operator);
        }

        return $this->matchZipCode($address);
    }

    public function getName(): string
    {
        return 'customerShippingZipCode';
    }
}
