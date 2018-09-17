<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Rule;

use Shopware\Core\Checkout\CheckoutRuleScope;
use Shopware\Core\Framework\Rule\Match;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleScope;

class CustomerNumberRule extends Rule
{
    /**
     * @var string[]
     */
    protected $numbers;

    /**
     * @param string[] $numbers
     */
    public function __construct(array $numbers)
    {
        $this->numbers = array_map('strtolower', $numbers);
    }

    public function match(RuleScope $scope): Match
    {
        if (!$scope instanceof CheckoutRuleScope) {
            return new Match(false, ['Wrong scope']);
        }

        /** @var CheckoutRuleScope $scope */
        if (!$customer = $scope->getCheckoutContext()->getCustomer()) {
            return new Match(false, ['Not logged in customer']);
        }

        return new Match(
            in_array(strtolower($customer->getCustomerNumber()), $this->numbers, true),
            ['Customer number not match']
        );
    }
}
