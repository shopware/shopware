<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Rule;

use Shopware\Core\Framework\Rule\Exception\UnsupportedOperatorException;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleScope;
use Shopware\Core\Framework\Util\FloatComparator;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;

class CartAmountRule extends Rule
{
    protected float $amount;

    protected string $operator;

    public function __construct(string $operator = self:: OPERATOR_EQ, ?float $amount = null)
    {
        parent::__construct();

        $this->operator = $operator;
        $this->amount = (float) $amount;
    }

    /**
     * @throws UnsupportedOperatorException
     */
    public function match(RuleScope $scope): bool
    {
        if (!$scope instanceof CartRuleScope) {
            return false;
        }

        $cartAmount = $scope->getCart()->getPrice()->getTotalPrice();

        switch ($this->operator) {
            case self::OPERATOR_GTE:
                return FloatComparator::greaterThanOrEquals($cartAmount, $this->amount);

            case self::OPERATOR_LTE:
                return FloatComparator::lessThanOrEquals($cartAmount, $this->amount);

            case self::OPERATOR_GT:
                return FloatComparator::greaterThan($cartAmount, $this->amount);

            case self::OPERATOR_LT:
                return FloatComparator::lessThan($cartAmount, $this->amount);

            case self::OPERATOR_EQ:
                return FloatComparator::equals($cartAmount, $this->amount);

            case self::OPERATOR_NEQ:
                return FloatComparator::notEquals($cartAmount, $this->amount);

            default:
                throw new UnsupportedOperatorException($this->operator, self::class);
        }
    }

    public function getConstraints(): array
    {
        return [
            'amount' => [new NotBlank(), new Type('numeric')],
            'operator' => [
                new NotBlank(),
                new Choice(
                    [
                        self::OPERATOR_EQ,
                        self::OPERATOR_LTE,
                        self::OPERATOR_GTE,
                        self::OPERATOR_NEQ,
                        self::OPERATOR_GT,
                        self::OPERATOR_LT,
                    ]
                ),
            ],
        ];
    }

    public function getName(): string
    {
        return 'cartCartAmount';
    }
}
