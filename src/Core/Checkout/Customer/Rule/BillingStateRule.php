<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Rule;

use Shopware\Core\Checkout\CheckoutRuleScope;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Exception\UnsupportedOperatorException;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleComparison;
use Shopware\Core\Framework\Rule\RuleConfig;
use Shopware\Core\Framework\Rule\RuleScope;
use Shopware\Core\Framework\Validation\Constraint\ArrayOfUuid;
use Shopware\Core\System\Country\Aggregate\CountryState\CountryStateDefinition;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\NotBlank;

#[Package('services-settings')]
class BillingStateRule extends Rule
{
    final public const RULE_NAME = 'customerBillingState';

    /**
     * @internal
     *
     * @param list<string>|null $stateIds
     */
    public function __construct(
        protected string $operator = self::OPERATOR_EQ,
        protected ?array $stateIds = null
    ) {
        parent::__construct();
    }

    /**
     * @throws UnsupportedOperatorException
     */
    public function match(RuleScope $scope): bool
    {
        if (!$scope instanceof CheckoutRuleScope) {
            return false;
        }

        if (!$customer = $scope->getSalesChannelContext()->getCustomer()) {
            return RuleComparison::isNegativeOperator($this->operator);
        }

        if (!$address = $customer->getActiveBillingAddress()) {
            return RuleComparison::isNegativeOperator($this->operator);
        }

        $stateId = $address->getCountryStateId();
        $parameter = [$stateId];
        if ($stateId === '') {
            $parameter = [];
        }

        return RuleComparison::uuids($parameter, $this->stateIds, $this->operator);
    }

    public function getConstraints(): array
    {
        $constraints = [
            'operator' => [
                new NotBlank(),
                new Choice([self::OPERATOR_EQ, self::OPERATOR_NEQ, self::OPERATOR_EMPTY]),
            ],
        ];

        if ($this->operator === self::OPERATOR_EMPTY) {
            return $constraints;
        }

        $constraints['stateIds'] = [new NotBlank(), new ArrayOfUuid()];

        return $constraints;
    }

    public function getConfig(): RuleConfig
    {
        return (new RuleConfig())
            ->operatorSet(RuleConfig::OPERATOR_SET_STRING, true, true)
            ->entitySelectField('stateIds', CountryStateDefinition::ENTITY_NAME, true);
    }
}
