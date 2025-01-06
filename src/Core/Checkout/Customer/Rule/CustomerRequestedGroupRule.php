<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Rule;

use Shopware\Core\Checkout\CheckoutRuleScope;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupDefinition;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleComparison;
use Shopware\Core\Framework\Rule\RuleConfig;
use Shopware\Core\Framework\Rule\RuleConstraints;
use Shopware\Core\Framework\Rule\RuleScope;

#[Package('services-settings')]
class CustomerRequestedGroupRule extends Rule
{
    final public const RULE_NAME = 'customerRequestedGroup';

    /**
     * @internal
     *
     * @param list<string>|null $customerGroupIds
     */
    public function __construct(
        protected string $operator = self::OPERATOR_EQ,
        protected ?array $customerGroupIds = null
    ) {
        parent::__construct();
    }

    public function match(RuleScope $scope): bool
    {
        if (!$scope instanceof CheckoutRuleScope) {
            return false;
        }

        if (!$customer = $scope->getSalesChannelContext()->getCustomer()) {
            return RuleComparison::isNegativeOperator($this->operator);
        }

        $requestedCustomerGroupId = $customer->getRequestedGroupId();

        if ($requestedCustomerGroupId === null) {
            return RuleComparison::isNegativeOperator($this->operator);
        }

        return RuleComparison::uuids([$requestedCustomerGroupId], $this->customerGroupIds, $this->operator);
    }

    public function getConstraints(): array
    {
        $constraints = [
            'operator' => RuleConstraints::uuidOperators(),
        ];

        if ($this->operator === self::OPERATOR_EMPTY) {
            return $constraints;
        }

        $constraints['customerGroupIds'] = RuleConstraints::uuids();

        return $constraints;
    }

    public function getConfig(): RuleConfig
    {
        return (new RuleConfig())
            ->operatorSet(RuleConfig::OPERATOR_SET_STRING, true, true)
            ->entitySelectField('customerGroupIds', CustomerGroupDefinition::ENTITY_NAME, true);
    }
}
