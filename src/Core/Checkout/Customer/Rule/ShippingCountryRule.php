<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Rule;

use Shopware\Core\Checkout\CheckoutRuleScope;
use Shopware\Core\Framework\Rule\Exception\UnsupportedOperatorException;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleComparison;
use Shopware\Core\Framework\Rule\RuleConfig;
use Shopware\Core\Framework\Rule\RuleConstraints;
use Shopware\Core\Framework\Rule\RuleScope;
use Shopware\Core\System\Country\CountryDefinition;

class ShippingCountryRule extends Rule
{
    /**
     * @var string[]|null
     */
    protected $countryIds;

    /**
     * @var string
     */
    protected $operator;

    /**
     * @internal
     * @param string[] $countryIds
     */
    public function __construct(string $operator = self::OPERATOR_EQ, ?array $countryIds = null)
    {
        parent::__construct();
        $this->operator = $operator;
        $this->countryIds = $countryIds;
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
        return 'customerShippingCountry';
    }

    public function getConfig(): RuleConfig
    {
        return (new RuleConfig())
            ->operatorSet(RuleConfig::OPERATOR_SET_STRING, true, true)
            ->entitySelectField('countryIds', CountryDefinition::ENTITY_NAME, true);
    }
}
