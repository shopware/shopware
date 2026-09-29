<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Price;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Price\TaxRuleFingerprint;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\Tax\Aggregate\TaxRule\TaxRuleCollection;
use Shopware\Core\System\Tax\Aggregate\TaxRule\TaxRuleEntity;
use Shopware\Core\System\Tax\TaxCollection;
use Shopware\Core\System\Tax\TaxEntity;
use Shopware\Core\Test\Generator;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(TaxRuleFingerprint::class)]
class TaxRuleFingerprintTest extends TestCase
{
    private const TAX_ID = '0191b0e4b7a97243b0f2fca1f9b0d2a1';
    private const TAX_RULE_ID = '0191b0e4b7a97243b0f2fca1f9b0d2a2';

    #[DataProvider('taxRuleFingerprintProvider')]
    public function testFingerprintIsBuiltOnlyWhenTheBasisDiffersFromTheDisplayState(?string $basis, string $taxState, bool $expectsFingerprint): void
    {
        $fingerprint = TaxRuleFingerprint::build(self::context($basis, $taxState, self::taxes(19.0)));

        static::assertSame($expectsFingerprint, $fingerprint !== null);
    }

    public static function taxRuleFingerprintProvider(): \Generator
    {
        yield 'net basis derives the displayed gross from the country tax rate' => [
            CustomerGroupEntity::PRICE_BASIS_NET, CartPrice::TAX_STATE_GROSS, true,
        ];

        yield 'gross basis derives the displayed net from the country tax rate' => [
            CustomerGroupEntity::PRICE_BASIS_GROSS, CartPrice::TAX_STATE_NET, true,
        ];

        yield 'net basis prints the stored net for net display' => [
            CustomerGroupEntity::PRICE_BASIS_NET, CartPrice::TAX_STATE_NET, false,
        ];

        yield 'gross basis prints the stored gross for gross display' => [
            CustomerGroupEntity::PRICE_BASIS_GROSS, CartPrice::TAX_STATE_GROSS, false,
        ];

        yield 'net basis charges the stored net for tax free deliveries' => [
            CustomerGroupEntity::PRICE_BASIS_NET, CartPrice::TAX_STATE_FREE, false,
        ];

        yield 'gross basis charges the stored net for tax free deliveries' => [
            CustomerGroupEntity::PRICE_BASIS_GROSS, CartPrice::TAX_STATE_FREE, false,
        ];

        yield 'legacy basis prints the stored value for gross display' => [
            null, CartPrice::TAX_STATE_GROSS, false,
        ];

        yield 'legacy basis prints the stored value for net display' => [
            null, CartPrice::TAX_STATE_NET, false,
        ];

        yield 'legacy basis charges the stored net for tax free deliveries' => [
            null, CartPrice::TAX_STATE_FREE, false,
        ];
    }

    public function testFingerprintPrefersTheCountrySpecificTaxRuleOverTheBaseRate(): void
    {
        $shippedToAustria = self::context(CustomerGroupEntity::PRICE_BASIS_NET, CartPrice::TAX_STATE_GROSS, self::taxes(19.0, countryRate: 20.0));
        $baseRateOfTwenty = self::context(CustomerGroupEntity::PRICE_BASIS_NET, CartPrice::TAX_STATE_GROSS, self::taxes(20.0));

        static::assertSame(
            TaxRuleFingerprint::build($shippedToAustria),
            TaxRuleFingerprint::build($baseRateOfTwenty)
        );
    }

    private static function context(?string $basis, string $taxState, TaxCollection $taxes): SalesChannelContext
    {
        $context = Generator::generateSalesChannelContext(
            currentCustomerGroup: (new CustomerGroupEntity())->assign(['id' => Uuid::randomHex(), 'priceBasis' => $basis]),
            taxRules: $taxes,
        );
        $context->setTaxState($taxState);

        return $context;
    }

    private static function taxes(float $rate, ?float $countryRate = null): TaxCollection
    {
        $tax = (new TaxEntity())->assign([
            'id' => self::TAX_ID,
            '_uniqueIdentifier' => self::TAX_ID,
            'taxRate' => $rate,
            'name' => 'tax',
            'position' => 1,
        ]);

        if ($countryRate !== null) {
            $tax->setRules(new TaxRuleCollection([
                (new TaxRuleEntity())->assign([
                    'id' => self::TAX_RULE_ID,
                    '_uniqueIdentifier' => self::TAX_RULE_ID,
                    'taxRate' => $countryRate,
                ]),
            ]));
        }

        return new TaxCollection([$tax]);
    }
}
