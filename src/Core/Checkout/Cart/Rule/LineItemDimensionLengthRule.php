<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Rule;

use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryInformation;
use Shopware\Core\Checkout\Cart\Exception\PayloadKeyNotFoundException;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Framework\Rule\Exception\UnsupportedOperatorException;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleScope;
use Shopware\Core\Framework\Util\FloatComparator;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;

class LineItemDimensionLengthRule extends Rule
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
        return 'cartLineItemDimensionLength';
    }

    public function match(RuleScope $scope): bool
    {
        if ($scope instanceof LineItemScope) {
            return $this->matchWidthDimension($scope->getLineItem());
        }

        if (!$scope instanceof CartRuleScope) {
            return false;
        }

        foreach ($scope->getCart()->getLineItems()->getFlat() as $lineItem) {
            if ($this->matchWidthDimension($lineItem)) {
                return true;
            }
        }

        return false;
    }

    public function getConstraints(): array
    {
        $constraints = [
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
                        self::OPERATOR_EMPTY,
                    ]
                ),
            ],
        ];

        if ($this->operator === self::OPERATOR_EMPTY) {
            return $constraints;
        }

        $constraints['amount'] = [new NotBlank(), new Type('numeric')];

        return $constraints;
    }

    /**
     * @throws PayloadKeyNotFoundException
     * @throws UnsupportedOperatorException
     */
    private function matchWidthDimension(LineItem $lineItem): bool
    {
        $deliveryInformation = $lineItem->getDeliveryInformation();

        if (!$deliveryInformation instanceof DeliveryInformation) {
            return false;
        }

        $length = $deliveryInformation->getLength();

        if ($length === null) {
            return $this->operator === self::OPERATOR_EMPTY;
        }

        $this->amount = (float) $this->amount;

        switch ($this->operator) {
            case self::OPERATOR_GTE:
                return FloatComparator::greaterThanOrEquals($length, $this->amount);

            case self::OPERATOR_LTE:
                return FloatComparator::lessThanOrEquals($length, $this->amount);

            case self::OPERATOR_GT:
                return FloatComparator::greaterThan($length, $this->amount);

            case self::OPERATOR_LT:
                return FloatComparator::lessThan($length, $this->amount);

            case self::OPERATOR_EQ:
                return FloatComparator::equals($length, $this->amount);

            case self::OPERATOR_NEQ:
                return FloatComparator::notEquals($length, $this->amount);

            case self::OPERATOR_EMPTY:
                return false;

            default:
                throw new UnsupportedOperatorException($this->operator, self::class);
        }
    }
}
