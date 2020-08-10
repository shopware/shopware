<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Rule;

use Shopware\Core\Checkout\CheckoutRuleScope;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleScope;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Type;

class DifferentAddressesRule extends Rule
{
    /**
     * @var bool
     */
    protected $isDifferent;

    public function __construct(bool $isDifferent = true)
    {
        parent::__construct();
        $this->isDifferent = $isDifferent;
    }

    public function match(RuleScope $scope): bool
    {
        if (!$scope instanceof CheckoutRuleScope) {
            return false;
        }

        if (!$customer = $scope->getSalesChannelContext()->getCustomer()) {
            return false;
        }

        if ($this->isDifferent) {
            return $customer->getActiveBillingAddress()->getId() !== $customer->getActiveShippingAddress()->getId();
        }

        return $customer->getActiveBillingAddress()->getId() === $customer->getActiveShippingAddress()->getId();
    }

    public function getConstraints(): array
    {
        return [
            'isDifferent' => [new NotNull(), new Type('bool')],
        ];
    }

    public function getName(): string
    {
        return 'customerDifferentAddresses';
    }
}
