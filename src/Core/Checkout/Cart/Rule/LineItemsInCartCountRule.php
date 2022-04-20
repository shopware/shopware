<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Rule;

use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleComparison;
use Shopware\Core\Framework\Rule\RuleConstraints;
use Shopware\Core\Framework\Rule\RuleScope;

class LineItemsInCartCountRule extends Rule
{
    protected int $count;

    protected string $operator;

    public function getName(): string
    {
        return 'cartLineItemsInCartCount';
    }

    public function match(RuleScope $scope): bool
    {
        if (!$scope instanceof CartRuleScope) {
            return false;
        }

        return RuleComparison::numeric($scope->getCart()->getLineItems()->count(), $this->count, $this->operator);
    }

    public function getConstraints(): array
    {
        return [
            'count' => RuleConstraints::int(),
            'operator' => RuleConstraints::numericOperators(false),
        ];
    }
}
