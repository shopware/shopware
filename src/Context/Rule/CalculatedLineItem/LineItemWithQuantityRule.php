<?php declare(strict_types=1);

namespace Shopware\Context\Rule\CalculatedLineItem;

use Shopware\Context\Exception\UnsupportedOperatorException;
use Shopware\Context\MatchContext\CalculatedLineItemMatchContext;
use Shopware\Context\MatchContext\RuleMatchContext;
use Shopware\Context\Rule\Match;
use Shopware\Context\Rule\Rule;

class LineItemWithQuantityRule extends Rule
{
    /**
     * @var string
     */
    protected $id;

    /**
     * @var int
     */
    protected $quantity;

    /**
     * @var string
     */
    protected $operator;

    public function __construct(string $id, ?int $quantity = null, string $operator = self::OPERATOR_EQ)
    {
        $this->id = $id;
        $this->quantity = $quantity;
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

        if ($matchContext->getCalculatedLineItem()->getIdentifier() !== $this->id) {
            return new Match(
                false,
                ['CalculatedLineItem id does not match']
            );
        }

        if ($this->quantity !== null) {
            $quantity = $matchContext->getCalculatedLineItem()->getQuantity();

            switch ($this->operator) {
                case self::OPERATOR_GTE:
                    return new Match(
                        $quantity >= $this->quantity,
                        ['LineItem quantity too low']
                    );

                case self::OPERATOR_LTE:
                    return new Match(
                        $quantity <= $this->quantity,
                        ['LineItem quantity too high']
                    );

                case self::OPERATOR_EQ:
                    return new Match(
                        $quantity == $this->quantity,
                        ['LineItem quantity does not match']
                    );

                case self::OPERATOR_NEQ:
                    return new Match(
                        $quantity != $this->quantity,
                        ['LineItem quantity is equal']
                    );

                default:
                    throw new UnsupportedOperatorException($this->operator, __CLASS__);
            }
        }

        return new Match(true);
    }
}
