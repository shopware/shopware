<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Rule;

use Shopware\Core\Checkout\CheckoutRuleScope;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleComparison;
use Shopware\Core\Framework\Rule\RuleConstraints;
use Shopware\Core\Framework\Rule\RuleScope;

class CustomerNumberRule extends Rule
{
    /**
     * @var string[]
     */
    protected $numbers;

    /**
     * @var string
     */
    protected $operator;

    /**
     * @internal
     */
    public function __construct(string $operator = self::OPERATOR_EQ, ?array $numbers = null)
    {
        parent::__construct();
        $this->operator = $operator;
        $this->numbers = $numbers;
    }

    public function match(RuleScope $scope): bool
    {
        if (!$scope instanceof CheckoutRuleScope) {
            return false;
        }

        if (!$customer = $scope->getSalesChannelContext()->getCustomer()) {
            return false;
        }

        return RuleComparison::stringArray($customer->getCustomerNumber(), array_map('strtolower', $this->numbers), $this->operator);
    }

    public function getConstraints(): array
    {
        return [
            'numbers' => RuleConstraints::stringArray(),
            'operator' => RuleConstraints::stringOperators(false),
        ];
    }

    public function getName(): string
    {
        return 'customerCustomerNumber';
    }
}
