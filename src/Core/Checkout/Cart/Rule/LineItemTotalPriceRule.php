<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Rule;

use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Framework\Rule\Exception\UnsupportedOperatorException;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleScope;
use Shopware\Core\Framework\Util\FloatComparator;
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

    public function __construct(string $operator = self::OPERATOR_EQ, ?float $amount = null)
    {
        parent::__construct();

        $this->operator = $operator;
        $this->amount = $amount;
    }

    /**
     * @throws UnsupportedOperatorException
     */
    public function match(RuleScope $scope): bool
    {
        if ($scope instanceof LineItemScope) {
            return $this->lineItemMatches($scope->getLineItem());
        }

        if (!$scope instanceof CartRuleScope) {
            return false;
        }

        foreach ($scope->getCart()->getLineItems() as $lineItem) {
            if ($this->lineItemMatches($lineItem)) {
                return true;
            }
        }

        return false;
    }

    public function getConstraints(): array
    {
        return [
            'amount' => [new NotBlank(), new Type('numeric')],
            'operator' => [
                new NotBlank(),
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

    private function lineItemMatches(LineItem $lineItem): bool
    {
        $price = $lineItem->getPrice();

        if ($price === null) {
            return false;
        }

        $this->amount = (float) $this->amount;

        switch ($this->operator) {
            case self::OPERATOR_GTE:
                return FloatComparator::greaterThanOrEquals($price->getTotalPrice(), $this->amount);

            case self::OPERATOR_LTE:
                return FloatComparator::lessThanOrEquals($price->getTotalPrice(), $this->amount);

            case self::OPERATOR_GT:
                return FloatComparator::greaterThan($price->getTotalPrice(), $this->amount);

            case self::OPERATOR_LT:
                return FloatComparator::lessThan($price->getTotalPrice(), $this->amount);

            case self::OPERATOR_EQ:
                return FloatComparator::equals($price->getTotalPrice(), $this->amount);

            case self::OPERATOR_NEQ:
                return FloatComparator::notEquals($price->getTotalPrice(), $this->amount);

            default:
                throw new UnsupportedOperatorException($this->operator, self::class);
        }
    }
}
