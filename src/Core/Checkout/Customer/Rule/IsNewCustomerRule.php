<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Rule;

use Shopware\Core\Checkout\CheckoutRuleScope;
use Shopware\Core\Framework\Rule\Match;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleScope;

class IsNewCustomerRule extends Rule
{
    public function match(RuleScope $scope): Match
    {
        if (!$scope instanceof CheckoutRuleScope) {
            return new Match(false, ['Wrong scope']);
        }

        /** @var CheckoutRuleScope $scope */
        if (!$customer = $scope->getCheckoutContext()->getCustomer()) {
            return new Match(false, ['Not logged in customer']);
        }

        if (!$customer->getFirstLogin()) {
            return new Match(false, ['Never logged in']);
        }

        return new Match(
            $customer->getFirstLogin()->format('Y-m-d') === (new \DateTime())->format('Y-m-d'),
            ['Customer is not new']
        );
    }
}
