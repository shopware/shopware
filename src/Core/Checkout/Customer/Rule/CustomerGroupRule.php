<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Rule;

use Shopware\Core\Checkout\CheckoutRuleScope;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleComparison;
use Shopware\Core\Framework\Rule\RuleConstraints;
use Shopware\Core\Framework\Rule\RuleScope;

class CustomerGroupRule extends Rule
{
    /**
     * @var string[]
     */
    protected $customerGroupIds;

    /**
     * @var string
     */
    protected $operator;

    /**
     * @internal
     */
    public function __construct(string $operator = self::OPERATOR_EQ, ?array $customerGroupIds = null)
    {
        parent::__construct();
        $this->operator = $operator;
        $this->customerGroupIds = $customerGroupIds;
    }

    public function match(RuleScope $scope): bool
    {
        if (!$scope instanceof CheckoutRuleScope) {
            return false;
        }

        return RuleComparison::uuids([$scope->getSalesChannelContext()->getCurrentCustomerGroup()->getId()], $this->customerGroupIds, $this->operator);
    }

    public function getConstraints(): array
    {
        return [
            'customerGroupIds' => RuleConstraints::uuids(),
            'operator' => RuleConstraints::uuidOperators(false),
        ];
    }

    public function getName(): string
    {
        return 'customerCustomerGroup';
    }
}
