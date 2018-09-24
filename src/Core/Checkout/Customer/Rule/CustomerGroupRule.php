<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Rule;

use Shopware\Core\Checkout\CheckoutRuleScope;
use Shopware\Core\Framework\Rule\Match;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleScope;

class CustomerGroupRule extends Rule
{
    /**
     * @var int[]
     */
    protected $customerGroupIds;

    /**
     * @param int[] $customerGroupIds
     */
    public function __construct(array $customerGroupIds)
    {
        $this->customerGroupIds = $customerGroupIds;
    }

    public function match(RuleScope $scope): Match
    {
        if (!$scope instanceof CheckoutRuleScope) {
            return new Match(false, ['Wrong scope']);
        }

        /* @var CheckoutRuleScope $scope */
        return new Match(
            \in_array($scope->getCheckoutContext()->getCurrentCustomerGroup()->getId(), $this->customerGroupIds, true),
            ['Current customer group not matched']
        );
    }
}
