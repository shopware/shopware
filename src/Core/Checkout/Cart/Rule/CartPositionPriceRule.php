<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Rule;

use Shopware\Core\Framework\Rule\Exception\UnsupportedOperatorException;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleScope;
use Shopware\Core\Framework\Util\FloatComparator;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;

class CartPositionPriceRule extends Rule
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

        $positionPrice = $scope->getCart()->getPrice()->getPositionPrice();

        switch ($this->operator) {
            case self::OPERATOR_GTE:
                return FloatComparator::greaterThanOrEquals($positionPrice, $this->amount);

            case self::OPERATOR_LTE:
                return FloatComparator::lessThanOrEquals($positionPrice, $this->amount);

            case self::OPERATOR_GT:
                return FloatComparator::greaterThan($positionPrice, $this->amount);

            case self::OPERATOR_LT:
                return FloatComparator::lessThan($positionPrice, $this->amount);

            case self::OPERATOR_EQ:
                return FloatComparator::equals($positionPrice, $this->amount);

            case self::OPERATOR_NEQ:
                return FloatComparator::notEquals($positionPrice, $this->amount);

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
        return 'cartPositionPrice';
    }
}
