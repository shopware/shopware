<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Rule;

use Shopware\Core\Content\Rule\Exception\UnsupportedOperatorException;
use Shopware\Core\Framework\Rule\Match;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleScope;

class GoodsCountRule extends Rule
{
    /**
     * @var int
     */
    protected $count;

    /**
     * @var string
     */
    protected $operator;

    public function __construct(int $count, string $operator = self::OPERATOR_EQ)
    {
        $this->count = $count;
        $this->operator = $operator;
    }

    /**
     * @throws UnsupportedOperatorException
     */
    public function match(RuleScope $scope): Match
    {
        if (!$scope instanceof CartRuleScope) {
            return new Match(
                false,
                ['Invalid Match Context. CartRuleScope expected']
            );
        }

        $goods = $scope->getCart()->getLineItems()->filterGoods();

        switch ($this->operator) {
            case self::OPERATOR_GTE:

                return new Match(
                    $goods->count() >= $this->count,
                    ['Goods count too much']
                );

            case self::OPERATOR_LTE:

                return new Match(
                    $goods->count() <= $this->count,
                    ['Goods count too less']
                );

            case self::OPERATOR_EQ:

                return new Match(
                    $goods->count() === $this->count,
                    ['Goods count not equal']
                );

            case self::OPERATOR_NEQ:

                return new Match(
                    $goods->count() !== $this->count,
                    ['Goods count equal']
                );

            default:
                throw new UnsupportedOperatorException($this->operator, __CLASS__);
        }
    }
}
