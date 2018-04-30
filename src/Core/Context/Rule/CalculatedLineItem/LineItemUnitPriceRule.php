<?php declare(strict_types=1);

namespace Shopware\Context\Rule\CalculatedLineItem;

use Shopware\Context\Exception\UnsupportedOperatorException;
use Shopware\Context\MatchContext\CalculatedLineItemMatchContext;
use Shopware\Context\MatchContext\RuleMatchContext;
use Shopware\Context\Rule\Match;
use Shopware\Context\Rule\Rule;

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
        RuleMatchContext $matchContext
    ): Match {
        if (!$matchContext instanceof CalculatedLineItemMatchContext) {
            return new Match(
                false,
                ['Invalid Match Context. CalculatedLineItemMatchContext expected']
            );
        }

        $unitPrice = $matchContext->getCalculatedLineItem()->getPrice()->getUnitPrice();
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
