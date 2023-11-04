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

#[Package('business-ops')]
class CustomerGroupRule extends Rule
{
    final public const RULE_NAME = 'customerCustomerGroup';

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

        return RuleComparison::uuids([$scope->getSalesChannelContext()->getCurrentCustomerGroup()->getId()], $this->customerGroupIds, $this->operator);
    }

    public function getConstraints(): array
    {
        return [
            'customerGroupIds' => RuleConstraints::uuids(),
            'operator' => RuleConstraints::uuidOperators(false),
        ];
    }

    public function getConfig(): RuleConfig
    {
        return (new RuleConfig())
            ->operatorSet(RuleConfig::OPERATOR_SET_STRING, false, true)
            ->entitySelectField('customerGroupIds', CustomerGroupDefinition::ENTITY_NAME, true);
    }
}
