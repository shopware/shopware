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
class CartShippingCostRule extends Rule
{
    public const RULE_NAME = 'cartShippingCost';

    protected ?float $cartShippingCost;

    protected string $operator;

    /**
     * @internal
     */
    public function __construct(
        string $operator = self::OPERATOR_EQ,
        ?float $cartShippingCost = null
    ) {
        parent::__construct();

        $this->operator = $operator;
        $this->cartShippingCost = $cartShippingCost;
    }

    public function match(RuleScope $scope): bool
    {
        if (!$scope instanceof CartRuleScope) {
            return false;
        }

        return RuleComparison::numeric($this->fetchShippingCosts($scope->getCart()), $this->cartShippingCost, $this->operator);
    }

    public function getConstraints(): array
    {
        return [
            'cartShippingCost' => RuleConstraints::float(),
            'operator' => RuleConstraints::numericOperators(false),
        ];
    }

    public function getConfig(): RuleConfig
    {
        return (new RuleConfig())
            ->operatorSet(RuleConfig::OPERATOR_SET_NUMBER)
            ->numberField('cartShippingCost');
    }

    private function fetchShippingCosts(Cart $cart): float
    {
        return $cart->getShippingCosts()->getTotalPrice();
    }
}
