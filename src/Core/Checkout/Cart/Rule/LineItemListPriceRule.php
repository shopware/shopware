<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Rule;

use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\ListPrice;
use Shopware\Core\Framework\Rule\Exception\UnsupportedOperatorException;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleScope;
use Shopware\Core\Framework\Util\FloatComparator;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;

class LineItemListPriceRule extends Rule
{
    protected ?float $amount;

    protected string $operator;

    public function __construct(string $operator = self::OPERATOR_EQ, ?float $amount = null)
    {
        parent::__construct();

        $this->operator = $operator;
        $this->amount = $amount;
    }

    public function getName(): string
    {
        return 'cartLineItemListPrice';
    }

    public function match(RuleScope $scope): bool
    {
        if ($scope instanceof LineItemScope) {
            return $this->matchesListPriceCondition($scope->getLineItem());
        }

        if (!$scope instanceof CartRuleScope) {
            return false;
        }

        foreach ($scope->getCart()->getLineItems()->getFlat() as $lineItem) {
            if ($this->matchesListPriceCondition($lineItem)) {
                return true;
            }
        }

        return false;
    }

    public function getConstraints(): array
    {
        return [
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
     * @throws UnsupportedOperatorException
     */
    private function matchesListPriceCondition(LineItem $lineItem): bool
    {
        $calculatedPrice = $lineItem->getPrice();

        if (!$calculatedPrice instanceof CalculatedPrice) {
            return false;
        }

        $listPrice = $calculatedPrice->getListPrice();

        if (!$listPrice instanceof ListPrice) {
            return false;
        }

        $listPriceAmount = $listPrice->getPrice();

        $this->amount = (float) $this->amount;

        switch ($this->operator) {
            case self::OPERATOR_GTE:
                return FloatComparator::greaterThanOrEquals($listPriceAmount, $this->amount);

            case self::OPERATOR_LTE:
                return FloatComparator::lessThanOrEquals($listPriceAmount, $this->amount);

            case self::OPERATOR_GT:
                return FloatComparator::greaterThan($listPriceAmount, $this->amount);

            case self::OPERATOR_LT:
                return FloatComparator::lessThan($listPriceAmount, $this->amount);

            case self::OPERATOR_EQ:
                return FloatComparator::equals($listPriceAmount, $this->amount);

            case self::OPERATOR_NEQ:
                return FloatComparator::notEquals($listPriceAmount, $this->amount);

            default:
                throw new UnsupportedOperatorException($this->operator, self::class);
        }
    }
}
