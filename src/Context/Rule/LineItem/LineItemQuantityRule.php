<?php declare(strict_types=1);

namespace Shopware\Context\Rule\LineItem;

use Shopware\Cart\LineItem\CalculatedLineItem;
use Shopware\Context\Exception\UnsupportedOperatorException;
use Shopware\Context\Rule\Match;
use Shopware\Context\Struct\StorefrontContext;

class LineItemQuantityRule extends LineItemRule
{
    /**
     * @var int
     */
    protected $quantity;

    /**
     * @var string
     */
    protected $operator;

    public function __construct(int $quantity, string $operator)
    {
        $this->quantity = $quantity;
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
                    $calculatedLineItem->getQuantity() >= $this->quantity,
                    ['LineItem quantity too low']
                );

            case self::OPERATOR_LTE:

                return new Match(
                    $calculatedLineItem->getQuantity() <= $this->quantity,
                    ['LineItem quantity too high']
                );

            default:
                throw new UnsupportedOperatorException($this->operator, __CLASS__);
        }
    }
}
