<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Rule;

use Shopware\Core\Content\Rule\Exception\UnsupportedOperatorException;
use Shopware\Core\Framework\Rule\Match;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleScope;

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

    /**
     * @throws UnsupportedOperatorException
     */
    public function match(
        RuleScope $scope
    ): Match {
        if (!$scope instanceof CartRuleScope) {
            return new Match(
                false,
                ['Invalid Match Context. CartRuleScope expected']
            );
        }
        $goods = $scope->getCart()->getLineItems()->filterGoods();

        $goodsAmount = $goods->getPrices()->sum()->getTotalPrice();

        switch ($this->operator) {
            case self::OPERATOR_GTE:

                return new Match(
                    $goodsAmount >= $this->amount,
                    ['Goods price too low']
                );

            case self::OPERATOR_LTE:

                return new Match(
                    $goodsAmount <= $this->amount,
                    ['Goods price too high']
                );

            case self::OPERATOR_EQ:
                return new Match(
                    $goodsAmount === $this->amount,
                    ['Goods price is not equal']
                );

            case self::OPERATOR_NEQ:

                return new Match(
                    $goodsAmount !== $this->amount,
                    ['Goods price is equal']
                );

            default:
                throw new UnsupportedOperatorException($this->operator, __CLASS__);
        }
    }
}
