<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Rule;

use Shopware\Core\Checkout\CheckoutRuleScope;
use Shopware\Core\Framework\Rule\Exception\UnsupportedOperatorException;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleScope;
use Shopware\Core\Framework\Util\FloatComparator;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;

class OrderTotalAmountRule extends Rule
{
    protected string $operator;

    protected float $amount;

    public function getName(): string
    {
        return 'customerOrderTotalAmount';
    }

    public function match(RuleScope $scope): bool
    {
        if (!$scope instanceof CheckoutRuleScope) {
            return false;
        }

        $customer = $scope->getSalesChannelContext()->getCustomer();

        if (!$customer) {
            return false;
        }

        $amount = $customer->getOrderTotalAmount();

        switch ($this->operator) {
            case self::OPERATOR_GTE:
                return FloatComparator::greaterThanOrEquals($amount, $this->amount);

            case self::OPERATOR_LTE:
                return FloatComparator::lessThanOrEquals($amount, $this->amount);

            case self::OPERATOR_GT:
                return FloatComparator::greaterThan($amount, $this->amount);

            case self::OPERATOR_LT:
                return FloatComparator::lessThan($amount, $this->amount);

            case self::OPERATOR_EQ:
                return FloatComparator::equals($amount, $this->amount);

            case self::OPERATOR_NEQ:
                return FloatComparator::notEquals($amount, $this->amount);
            default:
                throw new UnsupportedOperatorException($this->operator, self::class);
        }
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
}
