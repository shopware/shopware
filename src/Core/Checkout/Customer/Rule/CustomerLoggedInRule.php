<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Rule;

use Shopware\Core\Checkout\CheckoutRuleScope;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleScope;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Type;

class CustomerLoggedInRule extends Rule
{
    protected bool $isLoggedIn;

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
            'isLoggedIn' => [new NotNull(), new Type('bool')],
        ];
    }

    public function getName(): string
    {
        return 'customerLoggedIn';
    }
}
