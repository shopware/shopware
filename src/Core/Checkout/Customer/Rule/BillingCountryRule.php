<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Rule;

use Shopware\Core\Checkout\CheckoutRuleScope;
use Shopware\Core\Framework\Rule\Match;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleScope;
use Shopware\Core\Framework\Validation\Constraint\ArrayOfUuid;
use Symfony\Component\Validator\Constraints\NotBlank;

class BillingCountryRule extends Rule
{
    /**
     * @var string[]
     */
    protected $countryIds;

    public function match(RuleScope $scope): Match
    {
        if (!$scope instanceof CheckoutRuleScope) {
            return new Match(false, ['Wrong scope']);
        }

        /** @var CheckoutRuleScope $scope */
        if (!$customer = $scope->getCheckoutContext()->getCustomer()) {
            return new Match(false, ['Not logged in customer']);
        }

        $id = $customer->getActiveBillingAddress()->getCountry()->getId();

        return new Match(
            $id !== null && \in_array($id, $this->countryIds, true),
            ['Billing country not matched']
        );
    }

    public static function getConstraints(): array
    {
        return [
            'countryIds' => [new NotBlank(), new ArrayOfUuid()],
        ];
    }
}
