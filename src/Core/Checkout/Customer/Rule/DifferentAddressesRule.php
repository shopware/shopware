<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Rule;

use Shopware\Core\Checkout\CheckoutRuleScope;
use Shopware\Core\Framework\Rule\Match;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleScope;
use Symfony\Component\Validator\Constraints\Type;

class DifferentAddressesRule extends Rule
{
    /**
     * @var bool
     */
    protected $isDifferent;

    public function __construct()
    {
        $this->isDifferent = true;
    }

    public function match(RuleScope $scope): Match
    {
        if (!$scope instanceof CheckoutRuleScope) {
            return new Match(false, ['Wrong scope']);
        }

        /** @var CheckoutRuleScope $scope */
        if (!$customer = $scope->getSalesChannelContext()->getCustomer()) {
            return new Match(false, ['Not logged in customer']);
        }

        if ($this->isDifferent) {
            return new Match(
                $customer->getActiveBillingAddress()->getId() !== $customer->getActiveShippingAddress()->getId(),
                ['Addresses are equal']
            );
        }

        return new Match(
            $customer->getActiveBillingAddress()->getId() === $customer->getActiveShippingAddress()->getId(),
            ['Addresses are not equal']
        );
    }

    public function getConstraints(): array
    {
        return [
            'isDifferent' => [new Type('bool')],
        ];
    }

    public function getName(): string
    {
        return 'customerDifferentAddresses';
    }
}
