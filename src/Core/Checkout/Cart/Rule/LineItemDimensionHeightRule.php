<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Rule;

use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryInformation;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Framework\Rule\Exception\UnsupportedOperatorException;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleScope;
use Shopware\Core\Framework\Util\FloatComparator;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;

class LineItemDimensionHeightRule extends Rule
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
        return 'cartLineItemDimensionHeight';
    }

    public function match(RuleScope $scope): bool
    {
        if ($scope instanceof LineItemScope) {
            return $this->matchWeightDimension($scope->getLineItem());
        }

        if (!$scope instanceof CartRuleScope) {
            return false;
        }

        foreach ($scope->getCart()->getLineItems()->getFlat() as $lineItem) {
            if ($this->matchWeightDimension($lineItem)) {
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
     * @throws UnsupportedOperatorException
     */
    private function matchWeightDimension(LineItem $lineItem): bool
    {
        $deliveryInformation = $lineItem->getDeliveryInformation();

        if (!$deliveryInformation instanceof DeliveryInformation) {
            return false;
        }

        $height = $deliveryInformation->getHeight();

        if ($height === null) {
            return $this->operator === self::OPERATOR_EMPTY;
        }

        $this->amount = (float) $this->amount;

        switch ($this->operator) {
            case self::OPERATOR_GTE:
                return FloatComparator::greaterThanOrEquals($height, $this->amount);

            case self::OPERATOR_LTE:
                return FloatComparator::lessThanOrEquals($height, $this->amount);

            case self::OPERATOR_GT:
                return FloatComparator::greaterThan($height, $this->amount);

            case self::OPERATOR_LT:
                return FloatComparator::lessThan($height, $this->amount);

            case self::OPERATOR_EQ:
                return FloatComparator::equals($height, $this->amount);

            case self::OPERATOR_NEQ:
                return FloatComparator::notEquals($height, $this->amount);

            case self::OPERATOR_EMPTY:
                return false;

            default:
                throw new UnsupportedOperatorException($this->operator, self::class);
        }
    }
}
