<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Rule;

use Shopware\Core\Checkout\CheckoutRuleScope;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleConfig;
use Shopware\Core\Framework\Rule\RuleScope;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Type;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
class DifferentAddressesRule extends Rule
{
    final public const RULE_NAME = 'customerDifferentAddresses';

    public function __construct(
        protected bool $isDifferent = true
    ) {
        parent::__construct();
    }

    public function match(RuleScope $scope): bool
    {
        if (!$scope instanceof CheckoutRuleScope) {
            return false;
        }

        if (!$customer = $scope->getSalesChannelContext()->getCustomer()) {
            return false;
        }

        if (!$billingAddress = $customer->getActiveBillingAddress()) {
            return false;
        }

        if (!$shippingAddress = $customer->getActiveShippingAddress()) {
            return false;
        }

        if ($this->isDifferent) {
            return $billingAddress->getId() !== $shippingAddress->getId();
        }

        return $billingAddress->getId() === $shippingAddress->getId();
    }

    public function getConstraints(): array
    {
        return [
            'isDifferent' => [new NotNull(), new Type('bool')],
        ];
    }

    public function getConfig(): RuleConfig
    {
        return (new RuleConfig())
            ->booleanField('isDifferent');
    }
}
