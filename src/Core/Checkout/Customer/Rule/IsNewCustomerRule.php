<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Rule;

use Shopware\Core\Checkout\CheckoutRuleScope;
use Shopware\Core\Framework\Rule\Match;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleScope;
use Symfony\Component\Validator\Constraints\Type;

class IsNewCustomerRule extends Rule
{
    /**
     * @var bool
     */
    protected $isNew;

    public function __construct()
    {
        $this->isNew = true;
    }

    public function match(RuleScope $scope): Match
    {
        if (!$scope instanceof CheckoutRuleScope) {
            return new Match(false, ['Wrong scope']);
        }

        /** @var CheckoutRuleScope $scope */
        if (!$customer = $scope->getCheckoutContext()->getCustomer()) {
            return new Match(false, ['Not logged in customer']);
        }

        if (!$customer->getFirstLogin()) {
            return new Match(false, ['Never logged in']);
        }

        switch ($this->isNew) {
            case true:
                return new Match(
                    $customer->getFirstLogin()->format('Y-m-d') === (new \DateTime())->format('Y-m-d'),
                    ['Customer is not new']
                );
            case false:
                return new Match(
                    $customer->getFirstLogin()->format('Y-m-d') !== (new \DateTime())->format('Y-m-d'),
                    ['Customer is new']
                );
        }
    }

    public static function getConstraints(): array
    {
        return [
            'isNew' => [new Type('bool')],
        ];
    }

    public static function getName(): string
    {
        return 'is_new_customer';
    }
}
