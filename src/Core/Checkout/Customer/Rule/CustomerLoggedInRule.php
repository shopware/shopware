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
class CustomerLoggedInRule extends Rule
{
    protected bool $isLoggedIn;

    /**
     * @internal
     */
    public function __construct(bool $isLoggedIn = false)
    {
        parent::__construct();
        $this->isLoggedIn = $isLoggedIn;
    }

    public function match(RuleScope $scope): bool
    {
        if (!$scope instanceof CheckoutRuleScope) {
            return false;
        }

        $customer = $scope->getSalesChannelContext()->getCustomer();

        $loggedIn = $customer !== null;

        return $this->isLoggedIn === $loggedIn;
    }

    public function getConstraints(): array
    {
        return [
            'isLoggedIn' => RuleConstraints::bool(true),
        ];
    }

    public function getName(): string
    {
        return 'customerLoggedIn';
    }

    public function getConfig(): RuleConfig
    {
        return (new RuleConfig())
            ->booleanField('isLoggedIn');
    }
}
