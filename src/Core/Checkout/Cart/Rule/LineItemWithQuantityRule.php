<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Rule;

use Shopware\Core\Content\Rule\Exception\UnsupportedOperatorException;
use Shopware\Core\Framework\Rule\Match;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleScope;

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
        RuleScope $scope
    ): Match {
        if (!$scope instanceof LineItemScope) {
            return new Match(
                false,
                ['Invalid Match Context. LineItemScope expected']
            );
        }

        if ($scope->getLineItem()->getKey() !== $this->id) {
            return new Match(
                false,
                ['LineItem id does not match']
            );
        }

        if ($this->quantity !== null) {
            $quantity = $scope->getLineItem()->getQuantity();

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
