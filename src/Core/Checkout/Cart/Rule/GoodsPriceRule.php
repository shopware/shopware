<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Rule;

use Shopware\Core\Framework\Rule\Exception\UnsupportedOperatorException;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleScope;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;

class GoodsPriceRule extends Rule
{
    /**
     * @var float
     */
    protected $amount;

    /**
     * @var string
     */
    protected $operator;

    public function __construct()
    {
        $this->operator = self::OPERATOR_EQ;
    }

    /**
     * @throws UnsupportedOperatorException
     */
    public function match(RuleScope $scope): bool
    {
        if (!$scope instanceof CartRuleScope) {
            return false;
        }
        $goods = $scope->getCart()->getLineItems()->filterGoods();

        $goodsAmount = $goods->getPrices()->sum()->getTotalPrice();

        switch ($this->operator) {
            case self::OPERATOR_GTE:
                return $goodsAmount >= $this->amount;

            case self::OPERATOR_LTE:
                return $goodsAmount <= $this->amount;

            case self::OPERATOR_GT:
                return $goodsAmount > $this->amount;

            case self::OPERATOR_LT:
                return $goodsAmount < $this->amount;

            case self::OPERATOR_EQ:
                return $goodsAmount === $this->amount;

            case self::OPERATOR_NEQ:
                return $goodsAmount !== $this->amount;

            default:
                throw new UnsupportedOperatorException($this->operator, __CLASS__);
        }
    }

    public function getConstraints(): array
    {
        return [
            'amount' => [new NotBlank(), new Type('numeric')],
            'operator' => [
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

    public function getName(): string
    {
        return 'cartGoodsPrice';
    }
}
