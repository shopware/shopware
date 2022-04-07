<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Rule;

use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleComparison;
use Shopware\Core\Framework\Rule\RuleConstraints;
use Shopware\Core\Framework\Rule\RuleScope;

class LineItemOfTypeRule extends Rule
{
    protected string $lineItemType;

    protected string $operator;

    public function __construct(string $operator = self::OPERATOR_EQ, ?string $lineItemType = null)
    {
        parent::__construct();

        $this->operator = $operator;
        $this->lineItemType = (string) $lineItemType;
    }

    public function match(RuleScope $scope): bool
    {
        if ($scope instanceof LineItemScope) {
            return $this->lineItemMatches($scope->getLineItem());
        }

        if (!$scope instanceof CartRuleScope) {
            return false;
        }

        foreach ($scope->getCart()->getLineItems()->getFlat() as $lineItem) {
            if ($this->lineItemMatches($lineItem)) {
                return true;
            }
        }

        return false;
    }

    public function getConstraints(): array
    {
        return [
            'lineItemType' => RuleConstraints::string(),
            'operator' => RuleConstraints::stringOperators(false),
        ];
    }

    public function getName(): string
    {
        return 'cartLineItemOfType';
    }

    private function lineItemMatches(LineItem $lineItem): bool
    {
        return RuleComparison::string($lineItem->getType(), $this->lineItemType, $this->operator);
    }
}
