<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\Tax\TaxRuleType;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Delivery\Struct\ShippingLocation;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\Tax\Aggregate\TaxRule\TaxRuleEntity;
use Shopware\Core\System\Tax\Aggregate\TaxRuleType\TaxRuleTypeEntity;
use Shopware\Core\System\Tax\TaxRuleType\ZipCodeRuleTypeFilter;

/**
 * @internal
 */
#[CoversClass(ZipCodeRuleTypeFilter::class)]
class ZipCodeRuleTypeFilterTest extends TestCase
{
    public function testMatchesNotWithWrongType(): void
    {
        $type = new TaxRuleTypeEntity();
        $type->setTechnicalName('not me');

        $rule = new TaxRuleEntity();
        $rule->setActiveFrom(new \DateTime('2020-01-01'));
        $rule->setType($type);

        $filter = new ZipCodeRuleTypeFilter();
        static::assertFalse($filter->match($rule, null, new ShippingLocation(new CountryEntity(), null, null)));
    }

    public function testMatchesNotWithWithoutAddress(): void
    {
        $type = new TaxRuleTypeEntity();
        $type->setTechnicalName(ZipCodeRuleTypeFilter::TECHNICAL_NAME);

        $rule = new TaxRuleEntity();
        $rule->setActiveFrom(new \DateTime('2020-01-01'));
        $rule->setType($type);
        $rule->setCountryId('other-country-id');

        $filter = new ZipCodeRuleTypeFilter();
        static::assertFalse($filter->match($rule, null, new ShippingLocation($this->getCountry(), null, null)));
    }

    public function testMatchesNotWithWithWrongCountry(): void
    {
        $type = new TaxRuleTypeEntity();
        $type->setTechnicalName(ZipCodeRuleTypeFilter::TECHNICAL_NAME);

        $rule = new TaxRuleEntity();
        $rule->setActiveFrom(new \DateTime('2020-01-01'));
        $rule->setType($type);
        $rule->setCountryId('other-country-id');
        $rule->setData(['states' => ['state-id']]);

        $filter = new ZipCodeRuleTypeFilter();
        static::assertFalse($filter->match($rule, null, ShippingLocation::createFromAddress($this->getAddress())));
    }

    public function testMatchesNotWithWithWrongZipCode(): void
    {
        $type = new TaxRuleTypeEntity();
        $type->setTechnicalName(ZipCodeRuleTypeFilter::TECHNICAL_NAME);

        $rule = new TaxRuleEntity();
        $rule->setActiveFrom(new \DateTime('2020-01-01'));
        $rule->setType($type);
        $rule->setCountryId('country-id');
        $rule->setData(['zipCode' => 'other-zip-code']);

        $filter = new ZipCodeRuleTypeFilter();
        static::assertFalse($filter->match($rule, null, ShippingLocation::createFromAddress($this->getAddress())));
    }

    public function testMatchesNotWithFutureDate(): void
    {
        $type = new TaxRuleTypeEntity();
        $type->setTechnicalName(ZipCodeRuleTypeFilter::TECHNICAL_NAME);

        $rule = new TaxRuleEntity();
        $rule->setActiveFrom(new \DateTime('2040-01-01'));
        $rule->setType($type);
        $rule->setCountryId('country-id');
        $rule->setData(['zipCode' => 'zip-code']);

        $filter = new ZipCodeRuleTypeFilter();
        static::assertFalse($filter->match($rule, null, ShippingLocation::createFromAddress($this->getAddress())));
    }

    public function testMatchesWithPastDate(): void
    {
        $type = new TaxRuleTypeEntity();
        $type->setTechnicalName(ZipCodeRuleTypeFilter::TECHNICAL_NAME);

        $rule = new TaxRuleEntity();
        $rule->setActiveFrom(new \DateTime('2020-01-01'));
        $rule->setType($type);
        $rule->setCountryId('country-id');
        $rule->setData(['zipCode' => 'zip-code']);

        $filter = new ZipCodeRuleTypeFilter();
        static::assertTrue($filter->match($rule, null, ShippingLocation::createFromAddress($this->getAddress())));
    }

    public function testMatches(): void
    {
        $type = new TaxRuleTypeEntity();
        $type->setTechnicalName(ZipCodeRuleTypeFilter::TECHNICAL_NAME);

        $rule = new TaxRuleEntity();
        $rule->setType($type);
        $rule->setCountryId('country-id');
        $rule->setData(['zipCode' => 'zip-code']);

        $filter = new ZipCodeRuleTypeFilter();
        static::assertTrue($filter->match($rule, null, ShippingLocation::createFromAddress($this->getAddress())));
    }

    private function getCountry(): CountryEntity
    {
        $country = new CountryEntity();
        $country->setId('country-id');

        return $country;
    }

    private function getAddress(): CustomerAddressEntity
    {
        $address = new CustomerAddressEntity();
        $address->setId('address-id');
        $address->setZipcode('zip-code');
        $address->setCountry($this->getCountry());

        return $address;
    }
}
