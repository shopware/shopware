<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Rule;

use Shopware\Core\Checkout\CheckoutRuleScope;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupDefinition;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleComparison;
use Shopware\Core\Framework\Rule\RuleConfig;
use Shopware\Core\Framework\Rule\RuleConstraints;
use Shopware\Core\Framework\Rule\RuleScope;

/**
 * @package business-ops
 */
class CustomerGroupRule extends Rule
{
    public const RULE_NAME = 'customerCustomerGroup';

    /**
     * @var list<string>|null
     */
    protected ?array $customerGroupIds;

    protected string $operator;

    /**
     * @internal
     *
     * @param list<string>|null $customerGroupIds
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

    public function getConfig(): RuleConfig
    {
        return (new RuleConfig())
            ->operatorSet(RuleConfig::OPERATOR_SET_STRING, false, true)
            ->entitySelectField('customerGroupIds', CustomerGroupDefinition::ENTITY_NAME, true);
    }
}
