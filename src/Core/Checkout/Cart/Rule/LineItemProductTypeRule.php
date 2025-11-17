<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Rule;

use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Product\ProductTypeRegistry;
use Shopware\Core\Content\Product\State;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleComparison;
use Shopware\Core\Framework\Rule\RuleConfig;
use Shopware\Core\Framework\Rule\RuleConstraints;
use Shopware\Core\Framework\Rule\RuleScope;
use Shopware\Core\Framework\Feature;
use Symfony\Component\Validator\Constraint;

#[Package('fundamentals@after-sales')]
class LineItemProductTypeRule extends Rule
{
    final public const RULE_NAME = 'cartLineItemProductType';

    protected string $productType;

    protected string $operator;

    public function __construct(private readonly ProductTypeRegistry $productTypeRegistry)
    {
        parent::__construct();
    }

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
            'productType' => RuleConstraints::choice($this->productTypeRegistry->getTypes()),
        ];
    }

    public function getConfig(): RuleConfig
    {
        return (new RuleConfig())
            ->operatorSet(RuleConfig::OPERATOR_SET_STRING)
            ->selectField('productType', $this->productTypeRegistry->getTypes());
    }

    private function lineItemMatches(LineItem $lineItem): bool
    {
        $resolvedType = $lineItem->getProductType();

        if ($resolvedType === null && !Feature::isActive('v6.8.0.0')) {
            if (\in_array(State::IS_DOWNLOAD, $lineItem->getStates(), true)) {
                $resolvedType = ProductEntity::TYPE_DIGITAL;
            } elseif (\in_array(State::IS_PHYSICAL, $lineItem->getStates(), true)) {
                $resolvedType = ProductEntity::TYPE_PHYSICAL;
            }
        }

        if ($resolvedType === null) {
            return false;
        }

        return RuleComparison::string($resolvedType, $this->productType, $this->operator);
    }
}
