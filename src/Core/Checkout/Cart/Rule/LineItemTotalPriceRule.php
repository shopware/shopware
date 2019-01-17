<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Rule;

use Shopware\Core\Content\Rule\Exception\UnsupportedOperatorException;
use Shopware\Core\Framework\Rule\Match;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleScope;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;

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

    public function __construct()
    {
        $this->operator = self::OPERATOR_EQ;
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

    public function getConstraints(): array
    {
        return [
            'amount' => [new NotBlank(), new Type('numeric')],
            'operator' => [new Choice([self::OPERATOR_EQ, self::OPERATOR_LTE, self::OPERATOR_GTE, self::OPERATOR_NEQ])],
        ];
    }

    public function getName(): string
    {
        return 'cartLineItemTotalPrice';
    }
}
