<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Rule;

use Shopware\Core\Content\Rule\Exception\UnsupportedOperatorException;
use Shopware\Core\Framework\Rule\Match;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleScope;

class LineItemTotalPriceRule extends Rule
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
        if (!$scope instanceof LineItemScope) {
            return new Match(
                false,
                ['Invalid Match Context. LineItemScope expected']
            );
        }

        $lineItem = $scope->getLineItem();

        switch ($this->operator) {
            case self::OPERATOR_GTE:
                return new Match(
                    $lineItem->getPrice()->getTotalPrice() >= $this->amount,
                    ['LineItem total price too low']
                );

            case self::OPERATOR_LTE:
                return new Match(
                    $lineItem->getPrice()->getTotalPrice() <= $this->amount,
                    ['LineItem total price too high']
                );

            case self::OPERATOR_EQ:
                return new Match(
                    $lineItem->getPrice()->getTotalPrice() == $this->amount,
                    ['LineItem total price is not equal']
                );

            case self::OPERATOR_NEQ:
                return new Match(
                    $lineItem->getPrice()->getTotalPrice() != $this->amount,
                    ['LineItem total price is equal']
                );

            default:
                throw new UnsupportedOperatorException($this->operator, __CLASS__);
        }
    }
}
