<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Rule;

use Shopware\Core\Checkout\CheckoutRuleScope;
use Shopware\Core\Framework\Rule\Match;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleScope;
use Shopware\Core\Framework\Validation\Constraint\ArrayOfUuid;
use Symfony\Component\Validator\Constraints\NotBlank;

class CustomerGroupRule extends Rule
{
    /**
     * @var string[]
     */
    protected $customerGroupIds;

    public function match(RuleScope $scope): Match
    {
        if (!$scope instanceof CheckoutRuleScope) {
            return new Match(false, ['Wrong scope']);
        }

        $id = $scope->getCheckoutContext()->getCurrentCustomerGroup()->getId();

        /* @var CheckoutRuleScope $scope */
        return new Match(
            $id !== null && \in_array($id, $this->customerGroupIds, true),
            ['Current customer group not matched']
        );
    }

    public static function getConstraints(): array
    {
        return [
            'customerGroupIds' => [new NotBlank(), new ArrayOfUuid()],
        ];
    }
}
