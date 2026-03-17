<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Rule;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleComparison;
use Shopware\Core\Framework\Rule\RuleConfig;
use Shopware\Core\Framework\Rule\RuleConstraints;
use Shopware\Core\Framework\Rule\RuleScope;

/**
 * @final
 */
#[Package('fundamentals@after-sales')]
class CartTotalTaxRule extends Rule
{
    final public const RULE_NAME = 'cartTotalTax';

    protected string $operator = Rule::OPERATOR_EQ;

    protected float $amount = 0.0;

    public function match(RuleScope $scope): bool
    {
        if (!$scope instanceof CartRuleScope) {
            return false;
        }

        $total = $scope->getCart()->getPrice()->getCalculatedTaxes()->getAmount();

        return RuleComparison::numeric($total, $this->amount, $this->operator);
    }

    public function getConstraints(): array
    {
        return [
            'operator' => RuleConstraints::numericOperators(false),
            'amount' => RuleConstraints::float(),
        ];
    }

    public function getConfig(): RuleConfig
    {
        return (new RuleConfig())
            ->operatorSet(RuleConfig::OPERATOR_SET_NUMBER)
            ->numberField('amount');
    }
}
