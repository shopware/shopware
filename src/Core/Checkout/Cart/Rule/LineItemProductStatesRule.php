<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Rule;

use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Content\Product\State;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleComparison;
use Shopware\Core\Framework\Rule\RuleConfig;
use Shopware\Core\Framework\Rule\RuleConstraints;
use Shopware\Core\Framework\Rule\RuleScope;
use Symfony\Component\Validator\Constraint;

#[Package('business-ops')]
class LineItemProductStatesRule extends Rule
{
    final public const RULE_NAME = 'cartLineItemProductStates';

    protected string $productState;

    protected string $operator;

    public function match(RuleScope $scope): bool
    {
        if ($scope instanceof LineItemScope) {
            return $this->lineItemMatches($scope->getLineItem());
        }

        if (!$scope instanceof CartRuleScope) {
            return false;
        }

        foreach ($scope->getCart()->getLineItems()->filterGoodsFlat() as $lineItem) {
            if ($this->lineItemMatches($lineItem)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, array<int, Constraint>>
     */
    public function getConstraints(): array
    {
        return [
            'operator' => RuleConstraints::stringOperators(false),
            'productState' => RuleConstraints::choice([
                State::IS_PHYSICAL,
                State::IS_DOWNLOAD,
            ]),
        ];
    }

    public function getConfig(): RuleConfig
    {
        return (new RuleConfig())
            ->operatorSet(RuleConfig::OPERATOR_SET_STRING)
            ->selectField('productState', [
                State::IS_PHYSICAL,
                State::IS_DOWNLOAD,
            ]);
    }

    private function lineItemMatches(LineItem $lineItem): bool
    {
        return RuleComparison::stringArray($this->productState, array_values($lineItem->getStates()), $this->operator);
    }
}
