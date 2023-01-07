<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Rule;

use Shopware\Core\Checkout\CheckoutRuleScope;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleConfig;
use Shopware\Core\Framework\Rule\RuleConstraints;
use Shopware\Core\Framework\Rule\RuleScope;

/**
 * @package business-ops
 */
class IsGuestCustomerRule extends Rule
{
    protected bool $isGuest;

    /**
     * @internal
     */
    public function __construct(bool $isGuest = true)
    {
        parent::__construct();
        $this->isGuest = $isGuest;
    }

    public function match(RuleScope $scope): bool
    {
        if (!$scope instanceof CheckoutRuleScope) {
            return false;
        }

        if (!$customer = $scope->getSalesChannelContext()->getCustomer()) {
            return false;
        }

        if ($this->isGuest) {
            return $customer->getGuest();
        }

        return !$customer->getGuest();
    }

    public function getConstraints(): array
    {
        return [
            'isGuest' => RuleConstraints::bool(true),
        ];
    }

    public function getName(): string
    {
        return 'customerIsGuest';
    }

    public function getConfig(): RuleConfig
    {
        return (new RuleConfig())
            ->booleanField('isGuest');
    }
}
