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
        if (!$customer = $scope->getSalesChannelContext()->getCustomer()) {
            return new Match(false, ['Not logged in customer']);
        }

        if (!$customer->getFirstLogin()) {
            return new Match(false, ['Never logged in']);
        }

        if ($this->isNew) {
            return new Match(
                $customer->getFirstLogin()->format('Y-m-d') === (new \DateTime())->format('Y-m-d'),
                ['Customer is not new']
            );
        }

        return new Match(
            $customer->getFirstLogin()->format('Y-m-d') !== (new \DateTime())->format('Y-m-d'),
            ['Customer is new']
        );
    }

    public function getConstraints(): array
    {
        return [
            'isNew' => [new Type('bool')],
        ];
    }

    public function getName(): string
    {
        return 'customerIsNewCustomer';
    }
}
