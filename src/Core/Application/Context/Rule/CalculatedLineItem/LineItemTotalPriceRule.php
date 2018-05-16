<?php declare(strict_types=1);

namespace Shopware\Application\Context\Rule\CalculatedLineItem;

use Shopware\Application\Context\Exception\UnsupportedOperatorException;
use Shopware\Application\Context\Rule\MatchContext\CalculatedLineItemMatchContext;
use Shopware\Application\Context\Rule\MatchContext\RuleMatchContext;
use Shopware\Application\Context\Rule\Match;
use Shopware\Application\Context\Rule\Rule;

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
        RuleMatchContext $matchContext
    ): Match {
        if (!$matchContext instanceof CalculatedLineItemMatchContext) {
            return new Match(
                false,
                ['Invalid Match Context. CalculatedLineItemMatchContext expected']
            );
        }

        $calculatedLineItem = $matchContext->getCalculatedLineItem();

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
