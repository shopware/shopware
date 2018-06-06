<?php declare(strict_types=1);

namespace Shopware\Core\Content\Rule\Specification\CalculatedLineItem;

use Shopware\Core\Content\Rule\Exception\UnsupportedOperatorException;
use Shopware\Core\Content\Rule\Specification\Match;
use Shopware\Core\Content\Rule\Specification\Rule;
use Shopware\Core\Content\Rule\Specification\Scope\CalculatedLineItemScope;
use Shopware\Core\Content\Rule\Specification\Scope\RuleScope;

class LineItemUnitPriceRule extends Rule
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

        $unitPrice = $scope->getCalculatedLineItem()->getPrice()->getUnitPrice();
        switch ($this->operator) {
            case self::OPERATOR_GTE:

                return new Match(
                    $unitPrice >= $this->amount,
                    ['LineItem unit price too low']
                );

            case self::OPERATOR_LTE:

                return new Match(
                    $unitPrice <= $this->amount,
                    ['LineItem unit price too high']
                );

            case self::OPERATOR_EQ:

                return new Match(
                    $unitPrice == $this->amount,
                    ['LineItem unit price is not equal']
                );

            case self::OPERATOR_NEQ:

                return new Match(
                    $unitPrice != $this->amount,
                    ['LineItem unit price is equal']
                );

            default:
                throw new UnsupportedOperatorException($this->operator, __CLASS__);
        }
    }
}
