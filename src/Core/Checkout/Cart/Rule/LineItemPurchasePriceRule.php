<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Rule;

use Shopware\Core\Checkout\Cart\Exception\PayloadKeyNotFoundException;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Framework\Rule\Exception\UnsupportedOperatorException;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleScope;
use Shopware\Core\Framework\Util\FloatComparator;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Type;

class LineItemPurchasePriceRule extends Rule
{
    /**
     * @var float
     */
    protected $amount;

    /**
     * @var string
     */
    protected $operator;

    /**
     * @var bool
     */
    protected $isNet;

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

        foreach ($scope->getCart()->getLineItems() as $lineItem) {
            if ($this->matchPurchasePriceCondition($lineItem)) {
                return true;
            }
        }

        return false;
    }

    public function getConstraints(): array
    {
        return [
            'isNet' => [new NotNull(), new Type('bool')],
            'amount' => [new NotBlank(), new Type('numeric')],
            'operator' => [
                new NotBlank(),
                new Choice(
                    [
                        self::OPERATOR_NEQ,
                        self::OPERATOR_GTE,
                        self::OPERATOR_LTE,
                        self::OPERATOR_EQ,
                        self::OPERATOR_GT,
                        self::OPERATOR_LT,
                    ]
                ),
            ],
        ];
    }

    /**
     * @throws PayloadKeyNotFoundException
     * @throws UnsupportedOperatorException
     */
    private function matchPurchasePriceCondition(LineItem $lineItem): bool
    {
        $purchasePriceAmount = $this->getPurchasePriceAmount($lineItem);

        //@deprecated tag:v6.4.0 - Check for purchasePrice will be removed in 6.4.0 use purchasePrices instead
        if (!$purchasePriceAmount) {
            $purchasePriceAmount = $lineItem->getPayloadValue('purchasePrice');
        }

        if (!$purchasePriceAmount) {
            return false;
        }

        $this->amount = (float) $this->amount;

        /* @var float $purchasePriceAmount */
        switch ($this->operator) {
            case self::OPERATOR_GTE:
                return FloatComparator::greaterThanOrEquals($purchasePriceAmount, $this->amount);

            case self::OPERATOR_LTE:
                return FloatComparator::lessThanOrEquals($purchasePriceAmount, $this->amount);

            case self::OPERATOR_GT:
                return FloatComparator::greaterThan($purchasePriceAmount, $this->amount);

            case self::OPERATOR_LT:
                return FloatComparator::lessThan($purchasePriceAmount, $this->amount);

            case self::OPERATOR_EQ:
                return FloatComparator::equals($purchasePriceAmount, $this->amount);

            case self::OPERATOR_NEQ:
                return FloatComparator::notEquals($purchasePriceAmount, $this->amount);

            default:
                throw new UnsupportedOperatorException($this->operator, self::class);
        }
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
