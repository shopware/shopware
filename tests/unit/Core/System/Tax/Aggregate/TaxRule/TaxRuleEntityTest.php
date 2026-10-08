<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\Tax\Aggregate\TaxRule;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\Tax\Aggregate\TaxRule\TaxRuleEntity;
use Shopware\Core\System\Tax\Aggregate\TaxRuleType\TaxRuleTypeEntity;
use Shopware\Core\System\Tax\TaxEntity;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(TaxRuleEntity::class)]
class TaxRuleEntityTest extends TestCase
{
    public function testAccessorsRoundTrip(): void
    {
        $rule = new TaxRuleEntity();

        $tax = new TaxEntity();
        $country = new CountryEntity();
        $type = new TaxRuleTypeEntity();
        $activeFrom = new \DateTimeImmutable('2026-01-01 00:00:00');

        $rule->setTaxId('tax-id');
        $rule->setTax($tax);
        $rule->setCountryId('country-id');
        $rule->setCountry($country);
        $rule->setTaxRuleTypeId('type-id');
        $rule->setType($type);
        $rule->setTaxRate(19.0);
        $rule->setData(['zipCode' => '48624']);
        $rule->setActiveFrom($activeFrom);

        static::assertSame('tax-id', $rule->getTaxId());
        static::assertSame($tax, $rule->getTax());
        static::assertSame('country-id', $rule->getCountryId());
        static::assertSame($country, $rule->getCountry());
        static::assertSame('type-id', $rule->getTaxRuleTypeId());
        static::assertSame($type, $rule->getType());
        static::assertSame(19.0, $rule->getTaxRate());
        static::assertSame(['zipCode' => '48624'], $rule->getData());
        static::assertSame($activeFrom, $rule->getActiveFrom());
    }

    public function testGetTypeFallsBackToAnEmptyTypeWhenUnset(): void
    {
        static::assertEquals(new TaxRuleTypeEntity(), (new TaxRuleEntity())->getType());
    }
}
