<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Rule;

use Shopware\Core\Checkout\CheckoutRuleScope;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleComparison;
use Shopware\Core\Framework\Rule\RuleConfig;
use Shopware\Core\Framework\Rule\RuleConstraints;
use Shopware\Core\Framework\Rule\RuleScope;
use Shopware\Core\System\Country\CountryDefinition;

class BillingCountryRule extends Rule
{
    /**
     * @var array<string>|null
     */
    protected $countryIds;

    /**
     * @var string
     */
    protected $operator;

    /**
     * @internal
     *
     * @param array<string>|null $countryIds
     */
    public function __construct(string $operator = self::OPERATOR_EQ, ?array $countryIds = null)
    {
        parent::__construct();
        $this->operator = $operator;
        $this->countryIds = $countryIds;
    }

    public function match(RuleScope $scope): bool
    {
        if (!$scope instanceof CheckoutRuleScope) {
            return false;
        }
        if (!$customer = $scope->getSalesChannelContext()->getCustomer()) {
            if (!Feature::isActive('v6.5.0.0')) {
                return false;
            }

            return RuleComparison::isNegativeOperator($this->operator);
        }

        if (!$address = $customer->getActiveBillingAddress()) {
            if (!Feature::isActive('v6.5.0.0')) {
                return false;
            }

            return RuleComparison::isNegativeOperator($this->operator);
        }

        if (!$country = $address->getCountry()) {
            if (!Feature::isActive('v6.5.0.0')) {
                return false;
            }

            return RuleComparison::isNegativeOperator($this->operator);
        }

        $countryId = $country->getId();
        $parameter = [$countryId];
        if ($countryId === '') {
            $parameter = [];
        }

        return RuleComparison::uuids($parameter, $this->countryIds, $this->operator);
    }

    /**
     * @return array<string, mixed>
     */
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

    public function getName(): string
    {
        return 'customerBillingCountry';
    }

    public function getConfig(): RuleConfig
    {
        return (new RuleConfig())
            ->operatorSet(RuleConfig::OPERATOR_SET_STRING, true, true)
            ->entitySelectField('countryIds', CountryDefinition::ENTITY_NAME, true);
    }
}
