<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Rule;

use Shopware\Core\Checkout\CheckoutRuleScope;
use Shopware\Core\Framework\Rule\Container\DaysSinceRule;
use Shopware\Core\Framework\Rule\RuleScope;

/**
 * @package business-ops
 */
class DaysSinceFirstLoginRule extends DaysSinceRule
{
    public const RULE_NAME = 'customerDaysSinceFirstLogin';

    protected function getDate(RuleScope $scope): ?\DateTimeInterface
    {
        if (!$customer = $scope->getSalesChannelContext()->getCustomer()) {
            return null;
        }

        return $customer->getFirstLogin();
    }

    protected function supportsScope(RuleScope $scope): bool
    {
        return $scope instanceof CheckoutRuleScope;
    }
}
