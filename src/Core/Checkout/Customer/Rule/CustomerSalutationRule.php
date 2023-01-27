<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Rule;

use Shopware\Core\Checkout\CheckoutRuleScope;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleComparison;
use Shopware\Core\Framework\Rule\RuleConfig;
use Shopware\Core\Framework\Rule\RuleConstraints;
use Shopware\Core\Framework\Rule\RuleScope;
use Shopware\Core\System\Salutation\SalutationDefinition;

#[Package('business-ops')]
class CustomerSalutationRule extends Rule
{
    final public const RULE_NAME = 'customerSalutation';

    /**
     * @internal
     *
     * @param list<string>|null $salutationIds
     */
    public function __construct(
        public string $operator = self::OPERATOR_EQ,
        public ?array $salutationIds = null
    ) {
        parent::__construct();
    }

    public function getConstraints(): array
    {
        $constraints = [
            'operator' => RuleConstraints::uuidOperators(true),
        ];

        if ($this->operator === self::OPERATOR_EMPTY) {
            return $constraints;
        }

        $constraints['salutationIds'] = RuleConstraints::uuids();

        return $constraints;
    }

    public function match(RuleScope $scope): bool
    {
        if (!$scope instanceof CheckoutRuleScope) {
            return false;
        }

        if (!$customer = $scope->getSalesChannelContext()->getCustomer()) {
            return RuleComparison::isNegativeOperator($this->operator);
        }
        $salutation = $customer->getSalutation();

        if ($this->operator === self::OPERATOR_EMPTY) {
            return $salutation === null;
        }

        if ($salutation === null) {
            return RuleComparison::isNegativeOperator($this->operator);
        }

        return RuleComparison::uuids([$salutation->getId()], $this->salutationIds, $this->operator);
    }

    public function getConfig(): RuleConfig
    {
        return (new RuleConfig())
            ->operatorSet(RuleConfig::OPERATOR_SET_STRING, true, true)
            ->entitySelectField('salutationIds', SalutationDefinition::ENTITY_NAME, true, ['labelProperty' => 'displayName']);
    }
}
