<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Rule;

use Shopware\Core\Content\Rule\Exception\UnsupportedOperatorException;
use Shopware\Core\Framework\Rule\Match;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleScope;

class OrderAmountRule extends Rule
{
    /**
     * @var float
     */
    protected $amount;

    /**
     * @var string
     */
    protected $operator;

    public function __construct(float $amount, string $operator)
    {
        $this->amount = $amount;
        $this->operator = $operator;
    }

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
        $cartAmount = $scope->getCart()->getPrice()->getTotalPrice();

        switch ($this->operator) {
            case self::OPERATOR_GTE:

                return new Match(
                    $cartAmount >= $this->amount,
                    ['Total price too low']
                );

            case self::OPERATOR_LTE:

                return new Match(
                    $cartAmount <= $this->amount,
                    ['Total price too high']
                );

            case self::OPERATOR_EQ:

                return new Match(
                    $cartAmount == $this->amount,
                    ['Total price is not equal']
                );

            case self::OPERATOR_NEQ:

                return new Match(
                    $cartAmount != $this->amount,
                    ['Total price is equal']
                );

            default:
                throw new UnsupportedOperatorException($this->operator, __CLASS__);
        }
    }
}
