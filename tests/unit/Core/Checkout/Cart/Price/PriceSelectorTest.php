<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Price;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Price\PriceSelector;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\SelectedPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRule;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Cart\Tax\TaxCalculator;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\Price;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(PriceSelector::class)]
class PriceSelectorTest extends TestCase
{
    #[DataProvider('priceBasisProvider')]
    public function testSelect(?string $basis, string $taxState, float $expectedValue, bool $expectedIsCalculated): void
    {
        $selected = $this->select(
            new Price(Defaults::CURRENCY, 10.0, 11.9, false),
            new TaxRuleCollection([new TaxRule(19)]),
            $basis,
            $taxState
        );

        static::assertSame($expectedValue, $selected->getValue());
        static::assertSame($expectedIsCalculated, $selected->isCalculated());
    }

    public static function priceBasisProvider(): \Generator
    {
        yield 'legacy basis follows the display mode and takes the stored gross' => [
            null,
            CartPrice::TAX_STATE_GROSS,
            11.9,
            true,
        ];

        yield 'legacy basis follows the display mode and takes the stored net' => [
            null,
            CartPrice::TAX_STATE_NET,
            10.0,
            true,
        ];

        yield 'legacy basis takes the stored net for tax free deliveries' => [
            null,
            CartPrice::TAX_STATE_FREE,
            10.0,
            true,
        ];

        yield 'net basis hands the stored net to the gross calculator for derivation' => [
            CustomerGroupEntity::PRICE_BASIS_NET,
            CartPrice::TAX_STATE_GROSS,
            10.0,
            false,
        ];

        yield 'net basis takes the stored net as final value for net display' => [
            CustomerGroupEntity::PRICE_BASIS_NET,
            CartPrice::TAX_STATE_NET,
            10.0,
            true,
        ];

        yield 'net basis takes the stored net as final value for tax free deliveries' => [
            CustomerGroupEntity::PRICE_BASIS_NET,
            CartPrice::TAX_STATE_FREE,
            10.0,
            true,
        ];

        yield 'gross basis takes the stored gross as final value for gross display' => [
            CustomerGroupEntity::PRICE_BASIS_GROSS,
            CartPrice::TAX_STATE_GROSS,
            11.9,
            true,
        ];

        yield 'gross basis derives the net from the stored gross and ignores the stored net' => [
            CustomerGroupEntity::PRICE_BASIS_GROSS,
            CartPrice::TAX_STATE_NET,
            10.0,
            true,
        ];

        yield 'gross basis takes the stored net as final value for tax free deliveries' => [
            CustomerGroupEntity::PRICE_BASIS_GROSS,
            CartPrice::TAX_STATE_FREE,
            10.0,
            true,
        ];

        yield 'unknown basis falls back to the legacy display driven selection' => [
            'exact-net',
            CartPrice::TAX_STATE_GROSS,
            11.9,
            true,
        ];
    }

    public function testGrossBasisDerivesTheNetWithTheCountryTaxRate(): void
    {
        $price = new Price(Defaults::CURRENCY, 10.0, 11.9, false);

        $germany = $this->select($price, new TaxRuleCollection([new TaxRule(19)]), CustomerGroupEntity::PRICE_BASIS_GROSS, CartPrice::TAX_STATE_NET);
        $austria = $this->select($price, new TaxRuleCollection([new TaxRule(20)]), CustomerGroupEntity::PRICE_BASIS_GROSS, CartPrice::TAX_STATE_NET);

        // the fixed gross of 11.90 contains 1.90 tax in DE and 1.98 in AT, the net the merchant keeps varies with it
        static::assertSame(10.0, $germany->getValue());
        static::assertEqualsWithDelta(9.92, $austria->getValue(), 0.005);
    }

    public function testGrossBasisSplitsTheDerivedNetOverPartialTaxRules(): void
    {
        $selected = $this->select(
            new Price(Defaults::CURRENCY, 10.0, 11.9, false),
            new TaxRuleCollection([new TaxRule(19, 50), new TaxRule(7, 50)]),
            CustomerGroupEntity::PRICE_BASIS_GROSS,
            CartPrice::TAX_STATE_NET
        );

        static::assertEqualsWithDelta(10.56, $selected->getValue(), 0.005);
    }

    public function testGetDecoratedThrowsDecorationPatternException(): void
    {
        $selector = new PriceSelector(new TaxCalculator());

        $this->expectExceptionObject(new DecorationPatternException(PriceSelector::class));

        $selector->getDecorated();
    }

    private function select(Price $price, TaxRuleCollection $taxRules, ?string $basis, string $taxState): SelectedPrice
    {
        $customerGroup = (new CustomerGroupEntity())->assign(['priceBasis' => $basis]);

        $context = static::createStub(SalesChannelContext::class);
        $context->method('getCurrentCustomerGroup')->willReturn($customerGroup);
        $context->method('getTaxState')->willReturn($taxState);

        return (new PriceSelector(new TaxCalculator()))->select($price, $taxRules, $context);
    }
}
