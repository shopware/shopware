<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Price;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\CartException;
use Shopware\Core\Checkout\Cart\Price\CashRounding;
use Shopware\Core\Checkout\Cart\Price\CurrencyPriceCalculator;
use Shopware\Core\Checkout\Cart\Price\GrossPriceCalculator;
use Shopware\Core\Checkout\Cart\Price\NetPriceCalculator;
use Shopware\Core\Checkout\Cart\Price\PriceSelector;
use Shopware\Core\Checkout\Cart\Price\QuantityPriceCalculator;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\PriceCollection as CalculatedPriceCollection;
use Shopware\Core\Checkout\Cart\Tax\PercentageTaxRuleBuilder;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTax;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRule;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Cart\Tax\TaxCalculator;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\Price;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\PriceCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(CurrencyPriceCalculator::class)]
class CurrencyPriceCalculatorTest extends TestCase
{
    #[DataProvider('priceBasisProvider')]
    public function testPriceBasisDecidesWhichStoredValueIsAuthoritative(string $taxState, ?string $priceBasis, float $expectedUnitPrice): void
    {
        $price = $this->createCalculator()->calculate(
            new PriceCollection([new Price(Defaults::CURRENCY, 10.0, 99.99, false)]),
            $this->createReferencePrices(),
            $this->createContext($taxState, $priceBasis)
        );

        static::assertSame($expectedUnitPrice, $price->getUnitPrice());
    }

    public static function priceBasisProvider(): \Generator
    {
        yield 'legacy basis takes the stored gross for gross display' => [
            CartPrice::TAX_STATE_GROSS, null, 99.99,
        ];

        yield 'legacy basis takes the stored net for net display' => [
            CartPrice::TAX_STATE_NET, null, 10.0,
        ];

        yield 'net basis derives the gross from the stored net and ignores the stored gross' => [
            CartPrice::TAX_STATE_GROSS, CustomerGroupEntity::PRICE_BASIS_NET, 11.9,
        ];

        yield 'net basis takes the stored net verbatim for net display' => [
            CartPrice::TAX_STATE_NET, CustomerGroupEntity::PRICE_BASIS_NET, 10.0,
        ];
    }

    public function testMissingCurrencyPriceIsRejected(): void
    {
        $this->expectExceptionObject(CartException::invalidPriceDefinition());

        $context = $this->createContext(CartPrice::TAX_STATE_GROSS, null);

        $this->createCalculator()->calculate(
            new PriceCollection([new Price(Uuid::randomHex(), 10.0, 99.99, false)]),
            $this->createReferencePrices(),
            $context
        );
    }

    private function createCalculator(): CurrencyPriceCalculator
    {
        return new CurrencyPriceCalculator(
            new QuantityPriceCalculator(
                new GrossPriceCalculator(new TaxCalculator(), new CashRounding()),
                new NetPriceCalculator(new TaxCalculator(), new CashRounding())
            ),
            new PercentageTaxRuleBuilder(),
            new PriceSelector()
        );
    }

    private function createReferencePrices(): CalculatedPriceCollection
    {
        return new CalculatedPriceCollection([
            new CalculatedPrice(
                119.0,
                119.0,
                new CalculatedTaxCollection([new CalculatedTax(19.0, 19.0, 119.0)]),
                new TaxRuleCollection([new TaxRule(19)])
            ),
        ]);
    }

    private function createContext(string $taxState, ?string $priceBasis): SalesChannelContext
    {
        $context = static::createStub(SalesChannelContext::class);
        $context->method('getCurrencyId')->willReturn(Defaults::CURRENCY);
        $context->method('getTaxState')->willReturn($taxState);
        $context->method('getItemRounding')->willReturn(new CashRoundingConfig(2, 0.01, true));
        $context->method('getCurrentCustomerGroup')->willReturn(
            (new CustomerGroupEntity())->assign(['priceBasis' => $priceBasis])
        );

        return $context;
    }
}
