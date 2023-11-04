<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Rule;

use Shopware\Core\Checkout\CheckoutRuleScope;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Exception\UnsupportedOperatorException;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleComparison;
use Shopware\Core\Framework\Rule\RuleConfig;
use Shopware\Core\Framework\Rule\RuleConstraints;
use Shopware\Core\Framework\Rule\RuleScope;
use Shopware\Core\System\Country\CountryDefinition;

#[Package('business-ops')]
class ShippingCountryRule extends Rule
{
    final public const RULE_NAME = 'customerShippingCountry';

    /**
     * @internal
     *
     * @param list<string>|null $countryIds
     */
    public function __construct(
        protected string $operator = self::OPERATOR_EQ,
        protected ?array $countryIds = null
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

        $countryId = $scope->getSalesChannelContext()
            ->getShippingLocation()
            ->getCountry()
            ->getId();

        $parameter = [$countryId];
        if ($countryId === '') {
            $parameter = [];
        }

        return RuleComparison::uuids($parameter, $this->countryIds, $this->operator);
    }

    public function getConstraints(): array
    {
        $constraints = [
            'operator' => RuleConstraints::uuidOperators(),
        ];

        if ($this->operator === self::OPERATOR_EMPTY) {
            return $constraints;
        }

        $constraints['countryIds'] = RuleConstraints::uuids();

        return $constraints;
    }

    public function getConfig(): RuleConfig
    {
        return (new RuleConfig())
            ->operatorSet(RuleConfig::OPERATOR_SET_STRING, true, true)
            ->entitySelectField('countryIds', CountryDefinition::ENTITY_NAME, true);
    }
}
