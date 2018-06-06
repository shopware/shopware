<?php declare(strict_types=1);

namespace Shopware\Core\Content\Rule\Specification\CalculatedLineItem;

use Shopware\Core\Content\Rule\Exception\UnsupportedOperatorException;
use Shopware\Core\Content\Rule\Specification\Match;
use Shopware\Core\Content\Rule\Specification\Rule;
use Shopware\Core\Content\Rule\Specification\Scope\CalculatedLineItemScope;
use Shopware\Core\Content\Rule\Specification\Scope\RuleScope;

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

    public function __construct(float $amount, string $operator = self::OPERATOR_EQ)
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
        if (!$scope instanceof CalculatedLineItemScope) {
            return new Match(
                false,
                ['Invalid Match Context. CalculatedLineItemScope expected']
            );
        }

        $calculatedLineItem = $scope->getCalculatedLineItem();

        switch ($this->operator) {
            case self::OPERATOR_GTE:
                return new Match(
                    $calculatedLineItem->getPrice()->getTotalPrice() >= $this->amount,
                    ['LineItem total price too low']
                );

            case self::OPERATOR_LTE:
                return new Match(
                    $calculatedLineItem->getPrice()->getTotalPrice() <= $this->amount,
                    ['LineItem total price too high']
                );

            case self::OPERATOR_EQ:
                return new Match(
                    $calculatedLineItem->getPrice()->getTotalPrice() == $this->amount,
                    ['LineItem total price is not equal']
                );

            case self::OPERATOR_NEQ:
                return new Match(
                    $calculatedLineItem->getPrice()->getTotalPrice() != $this->amount,
                    ['LineItem total price is equal']
                );

            default:
                throw new UnsupportedOperatorException($this->operator, __CLASS__);
        }
    }
}
