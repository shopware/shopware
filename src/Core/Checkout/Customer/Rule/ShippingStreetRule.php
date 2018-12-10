<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Rule;

use Shopware\Core\Checkout\CheckoutRuleScope;
use Shopware\Core\Framework\Rule\Match;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleScope;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;

class ShippingStreetRule extends Rule
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
        if (!$location = $scope->getCheckoutContext()->getShippingLocation()->getAddress()) {
            return new Match(
                false,
                ['Shipping location has no address']
            );
        }

        $value = strtolower($this->streetName);

        return new Match(
            (bool) preg_match("/$value/", strtolower($location->getStreet())),
            ['Shipping street not matched']
        );
    }

    public static function getConstraints(): array
    {
        return [
            'streetName' => [new NotBlank(), new Type('string')],
        ];
    }
}
