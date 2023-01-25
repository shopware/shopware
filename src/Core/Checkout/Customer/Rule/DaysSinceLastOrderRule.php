<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Rule;

use Shopware\Core\Checkout\CheckoutRuleScope;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Container\DaysSinceRule;
use Shopware\Core\Framework\Rule\RuleScope;

#[Package('business-ops')]
class DaysSinceLastOrderRule extends DaysSinceRule
{
    final public const RULE_NAME = 'customerDaysSinceLastOrder';

    protected function getDate(RuleScope $scope): ?\DateTimeInterface
    {
        if (!$customer = $scope->getSalesChannelContext()->getCustomer()) {
            return null;
        }

        return $customer->getLastOrderDate();
    }

    protected function supportsScope(RuleScope $scope): bool
    {
        return $scope instanceof CheckoutRuleScope;
    }
}
