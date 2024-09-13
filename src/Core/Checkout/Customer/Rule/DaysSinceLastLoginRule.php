<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Rule;

use Shopware\Core\Checkout\CheckoutRuleScope;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Container\DaysSinceRule;
use Shopware\Core\Framework\Rule\RuleScope;

#[Package('services-settings')]
class DaysSinceLastLoginRule extends DaysSinceRule
{
    final public const RULE_NAME = 'customerDaysSinceLastLogin';

    protected function getDate(RuleScope $scope): ?\DateTimeInterface
    {
        return $scope->getSalesChannelContext()->getCustomer()?->getLastLogin();
    }

    protected function supportsScope(RuleScope $scope): bool
    {
        return $scope instanceof CheckoutRuleScope;
    }
}
