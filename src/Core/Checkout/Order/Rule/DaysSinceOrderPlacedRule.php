<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Rule;

use Shopware\Core\Content\Flow\Rule\FlowRuleScope;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Container\DaysSinceRule;
use Shopware\Core\Framework\Rule\RuleConfig;
use Shopware\Core\Framework\Rule\RuleConstraints;
use Shopware\Core\Framework\Rule\RuleScope;

/**
 * @final
 */
#[Package('after-sales')]
class DaysSinceOrderPlacedRule extends DaysSinceRule
{
    final public const RULE_NAME = 'daysSinceOrderPlaced';

    public function getConstraints(): array
    {
        return [
            'daysPassed' => RuleConstraints::int(),
            'operator' => RuleConstraints::numericOperators(false),
        ];
    }

    public function getConfig(): RuleConfig
    {
        return (new RuleConfig())
            ->operatorSet(RuleConfig::OPERATOR_SET_NUMBER)
            ->intField('daysPassed', ['unit' => RuleConfig::UNIT_TIME]);
    }

    protected function getDate(RuleScope $scope): ?\DateTimeInterface
    {
        return $scope instanceof FlowRuleScope ? $scope->getOrder()->getOrderDate() : null;
    }

    protected function supportsScope(RuleScope $scope): bool
    {
        return $scope instanceof FlowRuleScope;
    }
}
