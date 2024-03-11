<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\Tax\TaxRuleType;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Delivery\Struct\ShippingLocation;
use Shopware\Core\System\Country\Aggregate\CountryState\CountryStateEntity;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\Tax\Aggregate\TaxRule\TaxRuleEntity;
use Shopware\Core\System\Tax\Aggregate\TaxRuleType\TaxRuleTypeEntity;
use Shopware\Core\System\Tax\TaxRuleType\IndividualStatesRuleTypeFilter;

/**
 * @internal
 */
#[CoversClass(IndividualStatesRuleTypeFilter::class)]
class IndividualStatesRuleTypeFilterTest extends TestCase
{
    public function testMatchesNotWithWrongType(): void
    {
        $type = new TaxRuleTypeEntity();
        $type->setTechnicalName('not me');

        $rule = new TaxRuleEntity();
        $rule->setActiveFrom(new \DateTime('2020-01-01'));
        $rule->setType($type);

        $filter = new IndividualStatesRuleTypeFilter();
        static::assertFalse($filter->match($rule, null, new ShippingLocation(new CountryEntity(), null, null)));
    }

    public function testMatchesNotWithWithoutState(): void
    {
        $type = new TaxRuleTypeEntity();
        $type->setTechnicalName(IndividualStatesRuleTypeFilter::TECHNICAL_NAME);

        $rule = new TaxRuleEntity();
        $rule->setActiveFrom(new \DateTime('2020-01-01'));
        $rule->setType($type);
        $rule->setCountryId('other-country-id');

        $filter = new IndividualStatesRuleTypeFilter();
        static::assertFalse($filter->match($rule, null, new ShippingLocation($this->getCountry(), null, null)));
    }

    public function testMatchesNotWithWithWrongCountry(): void
    {
        $type = new TaxRuleTypeEntity();
        $type->setTechnicalName(IndividualStatesRuleTypeFilter::TECHNICAL_NAME);

        $rule = new TaxRuleEntity();
        $rule->setActiveFrom(new \DateTime('2020-01-01'));
        $rule->setType($type);
        $rule->setCountryId('other-country-id');
        $rule->setData(['states' => ['state-id']]);

        $filter = new IndividualStatesRuleTypeFilter();
        static::assertFalse($filter->match($rule, null, new ShippingLocation($this->getCountry(), $this->getState(), null)));
    }

    public function testMatchesNotWithWithWrongState(): void
    {
        $type = new TaxRuleTypeEntity();
        $type->setTechnicalName(IndividualStatesRuleTypeFilter::TECHNICAL_NAME);

        $rule = new TaxRuleEntity();
        $rule->setActiveFrom(new \DateTime('2020-01-01'));
        $rule->setType($type);
        $rule->setCountryId('country-id');
        $rule->setData(['states' => ['other-state-id']]);

        $filter = new IndividualStatesRuleTypeFilter();
        static::assertFalse($filter->match($rule, null, new ShippingLocation($this->getCountry(), $this->getState(), null)));
    }

    public function testMatchesNotWithFutureDate(): void
    {
        $type = new TaxRuleTypeEntity();
        $type->setTechnicalName(IndividualStatesRuleTypeFilter::TECHNICAL_NAME);

        $rule = new TaxRuleEntity();
        $rule->setActiveFrom(new \DateTime('2040-01-01'));
        $rule->setType($type);
        $rule->setCountryId('country-id');
        $rule->setData(['states' => ['state-id']]);

        $filter = new IndividualStatesRuleTypeFilter();
        static::assertFalse($filter->match($rule, null, new ShippingLocation($this->getCountry(), $this->getState(), null)));
    }

    public function testMatchesWithPastDate(): void
    {
        $type = new TaxRuleTypeEntity();
        $type->setTechnicalName(IndividualStatesRuleTypeFilter::TECHNICAL_NAME);

        $rule = new TaxRuleEntity();
        $rule->setActiveFrom(new \DateTime('2020-01-01'));
        $rule->setType($type);
        $rule->setCountryId('country-id');
        $rule->setData(['states' => ['state-id']]);

        $filter = new IndividualStatesRuleTypeFilter();
        static::assertTrue($filter->match($rule, null, new ShippingLocation($this->getCountry(), $this->getState(), null)));
    }

    public function testMatches(): void
    {
        $type = new TaxRuleTypeEntity();
        $type->setTechnicalName(IndividualStatesRuleTypeFilter::TECHNICAL_NAME);

        $rule = new TaxRuleEntity();
        $rule->setType($type);
        $rule->setCountryId('country-id');
        $rule->setData(['states' => ['state-id']]);

        $filter = new IndividualStatesRuleTypeFilter();
        static::assertTrue($filter->match($rule, null, new ShippingLocation($this->getCountry(), $this->getState(), null)));
    }

    private function getCountry(): CountryEntity
    {
        $country = new CountryEntity();
        $country->setId('country-id');

        return $country;
    }

    private function getState(): CountryStateEntity
    {
        $state = new CountryStateEntity();
        $state->setId('state-id');

        return $state;
    }
}
