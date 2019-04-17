<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Rule;

use Shopware\Core\Framework\Rule\Exception\UnsupportedOperatorException;
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
    public function match(RuleScope $scope): bool
    {
        if (!$scope instanceof LineItemScope) {
            return false;
        }

        $lineItem = $scope->getLineItem();

        $this->amount = (float) $this->amount;

        switch ($this->operator) {
            case self::OPERATOR_GTE:
                return $lineItem->getPrice()->getTotalPrice() >= $this->amount;

            case self::OPERATOR_LTE:
                return $lineItem->getPrice()->getTotalPrice() <= $this->amount;

            case self::OPERATOR_GT:
                return $lineItem->getPrice()->getTotalPrice() > $this->amount;

            case self::OPERATOR_LT:
                return $lineItem->getPrice()->getTotalPrice() < $this->amount;

            case self::OPERATOR_EQ:
                return $lineItem->getPrice()->getTotalPrice() === $this->amount;

            case self::OPERATOR_NEQ:
                return $lineItem->getPrice()->getTotalPrice() !== $this->amount;

            default:
                throw new UnsupportedOperatorException($this->operator, __CLASS__);
        }
    }

    public function getConstraints(): array
    {
        return [
            'amount' => [new NotBlank(), new Type('numeric')],
            'operator' => [
                new Choice(
                    [
                        self::OPERATOR_EQ,
                        self::OPERATOR_LTE,
                        self::OPERATOR_GTE,
                        self::OPERATOR_NEQ,
                        self::OPERATOR_GT,
                        self::OPERATOR_LT,
                    ]
                ),
            ],
        ];
    }

    public function getName(): string
    {
        return 'cartLineItemTotalPrice';
    }
}
