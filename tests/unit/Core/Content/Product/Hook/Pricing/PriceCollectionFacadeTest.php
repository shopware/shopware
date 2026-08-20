<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\Hook\Pricing;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Facade\PriceFacade;
use Shopware\Core\Checkout\Cart\Facade\ScriptPriceStubs;
use Shopware\Core\Checkout\Cart\Price\CashRounding;
use Shopware\Core\Checkout\Cart\Price\GrossPriceCalculator;
use Shopware\Core\Checkout\Cart\Price\NetPriceCalculator;
use Shopware\Core\Checkout\Cart\Price\PercentagePriceCalculator;
use Shopware\Core\Checkout\Cart\Price\PriceSelector;
use Shopware\Core\Checkout\Cart\Price\QuantityPriceCalculator;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\PriceCollection as CalculatedPriceCollection;
use Shopware\Core\Checkout\Cart\Tax\PercentageTaxRuleBuilder;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRule;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Cart\Tax\TaxCalculator;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupEntity;
use Shopware\Core\Content\Product\Hook\Pricing\PriceCollectionFacade;
use Shopware\Core\Content\Product\ProductException;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\PartialEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\Price;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\PriceCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(PriceCollectionFacade::class)]
class PriceCollectionFacadeTest extends TestCase
{
    public function testChangeReplacesThePricesWithTheGivenQuantityGraduation(): void
    {
        $prices = new CalculatedPriceCollection([
            new CalculatedPrice(50.0, 50.0, new CalculatedTaxCollection(), new TaxRuleCollection()),
        ]);

        $facade = $this->createFacade($prices, $this->createContext(priceBasis: null));

        $facade->change([
            ['to' => 10, 'price' => new PriceCollection([new Price(Defaults::CURRENCY, 16.81, 20.0, false)])],
            ['to' => '', 'price' => new PriceCollection([new Price(Defaults::CURRENCY, 8.4, 10.0, false)])],
        ]);

        $graduation = [];
        foreach ($prices as $price) {
            $graduation[$price->getQuantity()] = $price->getUnitPrice();
        }

        static::assertSame([10 => 20.0, 11 => 10.0], $graduation);
    }

    #[DataProvider('priceBasisProvider')]
    public function testChangeSelectsTheValueTheCustomerGroupPriceBasisMakesAuthoritative(?string $priceBasis, float $expectedUnitPrice): void
    {
        $prices = new CalculatedPriceCollection();

        $facade = $this->createFacade($prices, $this->createContext($priceBasis));

        $stored = new PriceCollection([new Price(Defaults::CURRENCY, 10.0, 99.99, false)]);

        $facade->change([
            ['to' => 5, 'price' => $stored],
            ['to' => '', 'price' => $stored],
        ]);

        $price = $prices->first();

        static::assertInstanceOf(CalculatedPrice::class, $price);
        static::assertSame($expectedUnitPrice, $price->getUnitPrice());
    }

    public static function priceBasisProvider(): \Generator
    {
        yield 'legacy basis takes the stored gross for gross display' => [null, 99.99];

        yield 'net basis derives the gross from the stored net and ignores the stored gross' => [
            CustomerGroupEntity::PRICE_BASIS_NET, 11.9,
        ];
    }

    public function testChangeThrowsWithoutAPriceForTheOpenQuantityRange(): void
    {
        $facade = $this->createFacade(new CalculatedPriceCollection(), $this->createContext(priceBasis: null));

        $this->expectExceptionObject(ProductException::invalidPriceDefinition());

        $facade->change([
            ['to' => 10, 'price' => new PriceCollection([new Price(Defaults::CURRENCY, 8.4, 10.0, false)])],
        ]);
    }

    public function testChangeThrowsWhenNoPriceMatchesTheContextCurrency(): void
    {
        $facade = $this->createFacade(new CalculatedPriceCollection(), $this->createContext(priceBasis: null));

        $foreignCurrency = new PriceCollection([new Price(Uuid::randomHex(), 8.4, 10.0, false)]);

        $this->expectExceptionObject(ProductException::invalidPriceDefinition());

        $facade->change([
            ['to' => 5, 'price' => $foreignCurrency],
            ['to' => '', 'price' => $foreignCurrency],
        ]);
    }

    public function testResetEmptiesTheCollection(): void
    {
        $facade = $this->createFacade(
            new CalculatedPriceCollection([
                new CalculatedPrice(10.0, 10.0, new CalculatedTaxCollection(), new TaxRuleCollection()),
                new CalculatedPrice(20.0, 20.0, new CalculatedTaxCollection(), new TaxRuleCollection()),
            ]),
            $this->createContext(priceBasis: null)
        );

        static::assertCount(2, $facade);

        $facade->reset();

        static::assertCount(0, $facade);
    }

    public function testGetIteratorWrapsEveryPriceInAPriceFacade(): void
    {
        $facade = $this->createFacade(
            new CalculatedPriceCollection([
                new CalculatedPrice(10.0, 10.0, new CalculatedTaxCollection(), new TaxRuleCollection()),
            ]),
            $this->createContext(priceBasis: null)
        );

        $facades = iterator_to_array($facade);

        static::assertCount(1, $facades);
        static::assertContainsOnlyInstancesOf(PriceFacade::class, $facades);
        static::assertSame(10.0, $facades[0]->getUnit());
    }

    private function createFacade(CalculatedPriceCollection $prices, SalesChannelContext $context): PriceCollectionFacade
    {
        $quantityCalculator = new QuantityPriceCalculator(
            new GrossPriceCalculator(new TaxCalculator(), new CashRounding()),
            new NetPriceCalculator(new TaxCalculator(), new CashRounding())
        );

        $stubs = new ScriptPriceStubs(
            static::createStub(Connection::class),
            $quantityCalculator,
            new PercentagePriceCalculator(new CashRounding(), $quantityCalculator, new PercentageTaxRuleBuilder()),
            new PriceSelector(),
        );

        $product = (new PartialEntity())->assign(['taxId' => Uuid::randomHex()]);

        return new PriceCollectionFacade($product, $prices, $stubs, $context);
    }

    private function createContext(?string $priceBasis): SalesChannelContext
    {
        $context = static::createStub(SalesChannelContext::class);
        $context->method('getCurrencyId')->willReturn(Defaults::CURRENCY);
        $context->method('getTaxState')->willReturn(CartPrice::TAX_STATE_GROSS);
        $context->method('buildTaxRules')->willReturn(new TaxRuleCollection([new TaxRule(19)]));
        $context->method('getItemRounding')->willReturn(new CashRoundingConfig(2, 0.01, true));
        $context->method('getCurrentCustomerGroup')->willReturn(
            (new CustomerGroupEntity())->assign(['priceBasis' => $priceBasis])
        );

        return $context;
    }
}
