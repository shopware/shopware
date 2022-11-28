<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Rule;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleComparison;
use Shopware\Core\Framework\Rule\RuleConfig;
use Shopware\Core\Framework\Rule\RuleConstraints;
use Shopware\Core\Framework\Rule\RuleScope;

/**
 * @package business-ops
 */
class CartWeightRule extends Rule
{
    protected float $weight;

    protected string $operator;

    /**
     * @internal
     */
    public function __construct(string $operator = self::OPERATOR_EQ, ?float $weight = null)
    {
        parent::__construct();

        $this->operator = $operator;
        $this->weight = (float) $weight;
    }

    public function getName(): string
    {
        return 'cartWeight';
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

        foreach ($cart->getLineItems()->getFlat() as $lineItem) {
            $itemWeight = 0.0;
            if ($lineItem->getDeliveryInformation() !== null && $lineItem->getDeliveryInformation()->getWeight() !== null) {
                $itemWeight = $lineItem->getDeliveryInformation()->getWeight();
            }

            $weight += $itemWeight * $lineItem->getQuantity();
        }

        return $weight;
    }
}
