<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Rule;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleComparison;
use Shopware\Core\Framework\Rule\RuleConfig;
use Shopware\Core\Framework\Rule\RuleConstraints;
use Shopware\Core\Framework\Rule\RuleScope;

#[Package('business-ops')]
class CartWeightRule extends Rule
{
    final public const RULE_NAME = 'cartWeight';

    protected float $weight;

    /**
     * @internal
     */
    public function __construct(
        protected string $operator = self::OPERATOR_EQ,
        ?float $weight = null
    ) {
        parent::__construct();
        $this->weight = (float) $weight;
    }

    public function match(RuleScope $scope): bool
    {
        if (!$scope instanceof CartRuleScope) {
            return false;
        }

        return RuleComparison::numeric($this->calculateCartWeight($scope->getCart()), $this->weight, $this->operator);
    }

    public function getConstraints(): array
    {
        return [
            'weight' => RuleConstraints::float(),
            'operator' => RuleConstraints::numericOperators(false),
        ];
    }

    public function getConfig(): RuleConfig
    {
        return (new RuleConfig())
            ->operatorSet(RuleConfig::OPERATOR_SET_NUMBER)
            ->numberField('weight', ['unit' => RuleConfig::UNIT_WEIGHT]);
    }

    private function calculateCartWeight(Cart $cart): float
    {
        $weight = 0.0;

        foreach ($cart->getLineItems()->filterGoodsFlat() as $lineItem) {
            $itemWeight = 0.0;
            if ($lineItem->getDeliveryInformation() !== null && $lineItem->getDeliveryInformation()->getWeight() !== null) {
                $itemWeight = $lineItem->getDeliveryInformation()->getWeight();
            }

            $weight += $itemWeight * $lineItem->getQuantity();
        }

        return $weight;
    }
}
