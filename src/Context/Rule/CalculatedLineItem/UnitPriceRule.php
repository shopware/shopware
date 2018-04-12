<?php declare(strict_types=1);

namespace Shopware\Context\Rule\CalculatedLineItem;

use Shopware\Context\Exception\InvalidMatchContext;
use Shopware\Context\Exception\UnsupportedOperatorException;
use Shopware\Context\MatchContext\CalculatedLineItemMatchContext;
use Shopware\Context\MatchContext\RuleMatchContext;
use Shopware\Context\Rule\Match;
use Shopware\Context\Rule\Rule;

class UnitPriceRule extends Rule
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
     * Validate the current rule and returns a reason object which contains defines if the rule match and if not why not
     *
     * @throws UnsupportedOperatorException
     * @throws InvalidMatchContext
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
                    $calculatedLineItem->getPrice()->getUnitPrice() >= $this->amount,
                    ['LineItem unit price too low']
                );

            case self::OPERATOR_LTE:

                return new Match(
                    $calculatedLineItem->getPrice()->getUnitPrice() <= $this->amount,
                    ['LineItem unit price too high']
                );

            default:
                throw new UnsupportedOperatorException($this->operator, __CLASS__);
        }
    }
}
