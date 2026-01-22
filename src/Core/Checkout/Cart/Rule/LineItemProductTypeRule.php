<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Rule;

use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductTypeRegistry;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleComparison;
use Shopware\Core\Framework\Rule\RuleConfig;
use Shopware\Core\Framework\Rule\RuleConstraints;
use Shopware\Core\Framework\Rule\RuleScope;
use Symfony\Component\Validator\Constraint;

/**
 * @final
 */
#[Package('fundamentals@after-sales')]
class LineItemProductTypeRule extends Rule
{
    final public const RULE_NAME = 'cartLineItemProductType';

    protected string $productType;

    protected string $operator;

    /**
     * @internal
     */
    public function __construct(private readonly ?ProductTypeRegistry $productTypeRegistry = null)
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
            'productType' => RuleConstraints::choice($this->productTypeRegistry?->getTypes() ?? [
                ProductDefinition::TYPE_PHYSICAL,
                ProductDefinition::TYPE_DIGITAL,
            ]),
        ];
    }

    public function getConfig(): RuleConfig
    {
        return (new RuleConfig())
            ->operatorSet(RuleConfig::OPERATOR_SET_STRING)
            ->selectField('productType', $this->productTypeRegistry?->getTypes() ?? [
                ProductDefinition::TYPE_PHYSICAL,
                ProductDefinition::TYPE_DIGITAL,
            ]);
    }

    private function lineItemMatches(LineItem $lineItem): bool
    {
        if (!$lineItem->hasPayloadValue(LineItem::PAYLOAD_PRODUCT_TYPE)) {
            return false;
        }

        $resolvedType = $lineItem->getPayloadValue(LineItem::PAYLOAD_PRODUCT_TYPE);

        if (!\is_string($resolvedType)) {
            return false;
        }

        return RuleComparison::string($resolvedType, $this->productType, $this->operator);
    }
}
