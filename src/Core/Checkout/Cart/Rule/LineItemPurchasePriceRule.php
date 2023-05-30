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
class LineItemPurchasePriceRule extends Rule
{
    final public const RULE_NAME = 'cartLineItemPurchasePrice';

    /**
     * @internal
     */
    public function __construct(
        protected string $operator = self::OPERATOR_EQ,
        protected ?float $amount = null,
        protected bool $isNet = true
    ) {
        parent::__construct();
    }

    public function match(RuleScope $scope): bool
    {
        if ($scope instanceof LineItemScope) {
            return $this->matchPurchasePriceCondition($scope->getLineItem());
        }

        if (!$scope instanceof CartRuleScope) {
            return false;
        }

        foreach ($scope->getCart()->getLineItems()->filterGoodsFlat() as $lineItem) {
            if ($this->matchPurchasePriceCondition($lineItem)) {
                return true;
            }
        }

        return false;
    }

    public function getConstraints(): array
    {
        $constraints = [
            'operator' => RuleConstraints::numericOperators(),
            'isNet' => RuleConstraints::bool(),
        ];

        if ($this->operator === self::OPERATOR_EMPTY) {
            return $constraints;
        }

        $constraints['amount'] = RuleConstraints::float();
        $constraints['isNet'] = RuleConstraints::bool(true);

        return $constraints;
    }

    /**
     * @throws CartException
     * @throws UnsupportedOperatorException
     */
    private function matchPurchasePriceCondition(LineItem $lineItem): bool
    {
        $purchasePriceAmount = $this->getPurchasePriceAmount($lineItem);

        return RuleComparison::numeric($purchasePriceAmount, $this->amount, $this->operator);
    }

    private function getPurchasePriceAmount(LineItem $lineItem): ?float
    {
        $purchasePricePayload = $lineItem->getPayloadValue('purchasePrices');
        if (!$purchasePricePayload) {
            return null;
        }
        $purchasePrice = json_decode((string) $purchasePricePayload, true, 512, \JSON_THROW_ON_ERROR);

        if ($this->isNet && \array_key_exists('net', $purchasePrice)) {
            return $purchasePrice['net'];
        }

        if (\array_key_exists('gross', $purchasePrice)) {
            return $purchasePrice['gross'];
        }

        return null;
    }
}
