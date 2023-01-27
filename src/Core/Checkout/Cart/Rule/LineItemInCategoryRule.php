<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Rule;

use Shopware\Core\Checkout\Cart\CartException;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Exception\UnsupportedOperatorException;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleComparison;
use Shopware\Core\Framework\Rule\RuleConstraints;
use Shopware\Core\Framework\Rule\RuleScope;

#[Package('business-ops')]
class LineItemInCategoryRule extends Rule
{
    final public const RULE_NAME = 'cartLineItemInCategory';

    /**
     * @internal
     *
     * @param list<string> $categoryIds
     */
    public function __construct(
        protected string $operator = self::OPERATOR_EQ,
        protected array $categoryIds = []
    ) {
        parent::__construct();
    }

    public function match(RuleScope $scope): bool
    {
        if ($scope instanceof LineItemScope) {
            return $this->matchesOneOfCategory($scope->getLineItem());
        }

        if (!$scope instanceof CartRuleScope) {
            return false;
        }

        foreach ($scope->getCart()->getLineItems()->filterGoodsFlat() as $lineItem) {
            if ($this->matchesOneOfCategory($lineItem)) {
                return true;
            }
        }

        return false;
    }

    public function getConstraints(): array
    {
        $constraints = [
            'operator' => RuleConstraints::uuidOperators(),
        ];

        if ($this->operator === self::OPERATOR_EMPTY) {
            return $constraints;
        }

        $constraints['categoryIds'] = RuleConstraints::uuids();

        return $constraints;
    }

    /**
     * @throws UnsupportedOperatorException
     * @throws CartException
     */
    private function matchesOneOfCategory(LineItem $lineItem): bool
    {
        return RuleComparison::uuids($lineItem->getPayloadValue('categoryIds'), $this->categoryIds, $this->operator);
    }
}
