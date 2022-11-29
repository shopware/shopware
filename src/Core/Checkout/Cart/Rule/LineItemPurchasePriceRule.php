<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Rule;

use Shopware\Core\Checkout\Cart\Exception\PayloadKeyNotFoundException;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Rule\Exception\UnsupportedOperatorException;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleComparison;
use Shopware\Core\Framework\Rule\RuleConstraints;
use Shopware\Core\Framework\Rule\RuleScope;

/**
 * @package business-ops
 */
class LineItemPurchasePriceRule extends Rule
{
    protected ?float $amount;

    protected string $operator;

    protected bool $isNet;

    /**
     * @internal
     */
    public function __construct(string $operator = self::OPERATOR_EQ, ?float $amount = null, bool $isNet = true)
    {
        parent::__construct();

        $this->isNet = $isNet;
        $this->operator = $operator;
        $this->amount = $amount;
    }

    public function getName(): string
    {
        return 'cartLineItemPurchasePrice';
    }

    public function match(RuleScope $scope): bool
    {
        if ($scope instanceof LineItemScope) {
            return $this->matchPurchasePriceCondition($scope->getLineItem());
        }

        if (!$scope instanceof CartRuleScope) {
            return false;
        }

        foreach ($scope->getCart()->getLineItems()->getFlat() as $lineItem) {
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
     * @throws PayloadKeyNotFoundException
     * @throws UnsupportedOperatorException
     */
    private function matchPurchasePriceCondition(LineItem $lineItem): bool
    {
        $purchasePriceAmount = $this->getPurchasePriceAmount($lineItem);

        if ((!$purchasePriceAmount || !$this->amount) && !Feature::isActive('v6.5.0.0')) {
            return $this->operator === self::OPERATOR_EMPTY;
        }

        return RuleComparison::numeric($purchasePriceAmount, $this->amount, $this->operator);
    }

    private function getPurchasePriceAmount(LineItem $lineItem): ?float
    {
        $purchasePricePayload = $lineItem->getPayloadValue('purchasePrices');
        if (!$purchasePricePayload) {
            return null;
        }
        $purchasePrice = json_decode($purchasePricePayload);
        if (!$purchasePrice) {
            return null;
        }

        if ($this->isNet && property_exists($purchasePrice, 'net')) {
            return $purchasePrice->net;
        }

        if (property_exists($purchasePrice, 'gross')) {
            return $purchasePrice->gross;
        }

        return null;
    }
}
