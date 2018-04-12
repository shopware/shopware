<?php declare(strict_types=1);

namespace Shopware\Context\Rule\LineItem;

use Shopware\Cart\LineItem\CalculatedLineItem;
use Shopware\Context\Exception\UnsupportedOperatorException;
use Shopware\Context\Rule\Match;
use Shopware\Context\Struct\StorefrontContext;

class LineItemTotalPriceRule extends LineItemRule
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
     */
    public function match(
        CalculatedLineItem $calculatedLineItem,
        StorefrontContext $context
    ): Match {
        switch ($this->operator) {
            case self::OPERATOR_GTE:

                return new Match(
                    $calculatedLineItem->getPrice()->getTotalPrice() >= $this->amount,
                    ['LineItem price too low']
                );

            case self::OPERATOR_LTE:

                return new Match(
                    $calculatedLineItem->getPrice()->getTotalPrice() <= $this->amount,
                    ['LineItem price too high']
                );

            default:
                throw new UnsupportedOperatorException($this->operator, __CLASS__);
        }
    }
}
