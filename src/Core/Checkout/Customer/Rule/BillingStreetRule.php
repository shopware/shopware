<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Rule;

use Shopware\Core\Checkout\CheckoutRuleScope;
use Shopware\Core\Framework\Rule\Match;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleScope;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;

class BillingStreetRule extends Rule
{
    /**
     * @var string
     */
    protected $streetName;

    public function match(RuleScope $scope): Match
    {
        if (!$scope instanceof CheckoutRuleScope) {
            return new Match(false, ['Wrong scope']);
        }

        /** @var CheckoutRuleScope $scope */
        if (!$customer = $scope->getCheckoutContext()->getCustomer()) {
            return new Match(false, ['Not logged in customer']);
        }

        $value = strtolower($this->streetName);

        $street = $customer->getActiveBillingAddress()->getStreet();

        return new Match(
            (bool) preg_match("/$value/", strtolower($street)),
            ["Billing street not match /$value/"]
        );
    }

    public static function getConstraints(): array
    {
        return [
            'streetName' => [new NotBlank(), new Type('string')],
        ];
    }
}
