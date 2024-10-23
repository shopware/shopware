<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Facade;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Facade\PriceFacade;
use Shopware\Core\Checkout\Cart\Facade\ScriptPriceStubs;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Price\CashRounding;
use Shopware\Core\Checkout\Cart\Price\GrossPriceCalculator;
use Shopware\Core\Checkout\Cart\Price\NetPriceCalculator;
use Shopware\Core\Checkout\Cart\Price\PercentagePriceCalculator;
use Shopware\Core\Checkout\Cart\Price\QuantityPriceCalculator;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Checkout\Cart\Tax\PercentageTaxRuleBuilder;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRule;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Cart\Tax\TaxCalculator;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\Price;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\PriceCollection;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Stub\Framework\IdsCollection;

/**
 * @internal
 */
#[CoversClass(PriceFacade::class)]
class PriceFacadeTest extends TestCase
{
    public function testLineItemsGetUpdatePriceDefinition(): void
    {
        $item = new LineItem('test', 'test', 'temp');

        $original = new CalculatedPrice(1, 1, new CalculatedTaxCollection(), new TaxRuleCollection());
        $price = new PriceFacade($item, $original, $this->createMock(ScriptPriceStubs::class), $this->createMock(SalesChannelContext::class));

        $price->change(new PriceCollection([
            new Price(Defaults::CURRENCY, 2, 2, false),
        ]));

        static::assertInstanceOf(QuantityPriceDefinition::class, $item->getPriceDefinition());
        static::assertEquals(2, $item->getPriceDefinition()->getPrice());
        static::assertNull($item->getPrice());
    }

    public function testChangesAreAppliedDirectlyForEntities(): void
    {
        $item = new Entity();
        $original = new CalculatedPrice(1, 1, new CalculatedTaxCollection(), new TaxRuleCollection());
        $stubs = $this->createMock(ScriptPriceStubs::class);
        $stubs->method('calculateQuantity')->willReturn(new CalculatedPrice(2, 2, new CalculatedTaxCollection(), new TaxRuleCollection()));

        $price = new PriceFacade($item, $original, $stubs, $this->createMock(SalesChannelContext::class));

        $price->change(new PriceCollection([
            new Price(Defaults::CURRENCY, 2, 2, false),
        ]));

        static::assertEquals(2, $original->getUnitPrice());
    }

    #[DataProvider('providerChange')]
    public function testChange(string $currencyKey, string $taxState, float $unit, float $tax): void
    {
        $ids = new IdsCollection([
            'default' => Defaults::CURRENCY,
            'usd' => Uuid::randomHex(),
        ]);

        $price = $this->rampUpPriceFacade($ids, $currencyKey, $taxState);

        $update = new PriceCollection([
            new Price(Defaults::CURRENCY, 2, 5, false),
            new Price($ids->get('usd'), 1, 4, false),
        ]);

        $price->change($update);

        static::assertEquals($unit, $price->getUnit());
        static::assertEquals($tax, $price->getTaxes()->getAmount());
    }

    #[DataProvider('providerDiscount')]
    public function testDiscount(string $taxState, float $unit, float $tax): void
    {
        $ids = new IdsCollection(['default' => Defaults::CURRENCY]);

        $price = $this->rampUpPriceFacade($ids, 'default', $taxState);

        $price->discount(20);

        static::assertEquals($unit, $price->getUnit());
        static::assertEquals($tax, $price->getTaxes()->getAmount());
    }

    #[DataProvider('providerSurcharge')]
    public function testSurcharge(string $taxState, float $unit, float $tax): void
    {
        $ids = new IdsCollection(['default' => Defaults::CURRENCY]);

        $price = $this->rampUpPriceFacade($ids, 'default', $taxState);

        $price->surcharge(20);

        static::assertEquals($unit, $price->getUnit());
        static::assertEquals($tax, $price->getTaxes()->getAmount());
    }

    #[DataProvider('providerPlus')]
    public function testPlus(string $currencyKey, string $taxState, float $unit, float $tax): void
    {
        $ids = new IdsCollection([
            'default' => Defaults::CURRENCY,
            'usd' => Uuid::randomHex(),
        ]);

        $price = $this->rampUpPriceFacade($ids, $currencyKey, $taxState);

        $update = new PriceCollection([
            new Price(Defaults::CURRENCY, 2, 5, false),
            new Price($ids->get('usd'), 1, 4, false),
        ]);

        $price->plus($update);

        static::assertEquals($unit, $price->getUnit());
        static::assertEquals($tax, $price->getTaxes()->getAmount());
    }

    #[DataProvider('providerMinus')]
    public function testMinus(string $currencyKey, string $taxState, float $unit, float $tax): void
    {
        $ids = new IdsCollection([
            'default' => Defaults::CURRENCY,
            'usd' => Uuid::randomHex(),
        ]);

        $price = $this->rampUpPriceFacade($ids, $currencyKey, $taxState);

        $update = new PriceCollection([
            new Price(Defaults::CURRENCY, 2, 5, false),
            new Price($ids->get('usd'), 1, 4, false),
        ]);

        $price->minus($update);

        static::assertEquals($unit, $price->getUnit());
        static::assertEquals($tax, $price->getTaxes()->getAmount());
    }

    public static function providerSurcharge(): \Generator
    {
        yield 'Test with gross prices' => [CartPrice::TAX_STATE_GROSS, 12.0, 1.09];
        yield 'Test with net prices' => [CartPrice::TAX_STATE_NET, 12, 1.2];
    }

    public static function providerDiscount(): \Generator
    {
        yield 'Test with gross prices' => [CartPrice::TAX_STATE_GROSS, 8, 0.73];
        yield 'Test with net prices' => [CartPrice::TAX_STATE_NET, 8, 0.8];
    }

    public static function providerChange(): \Generator
    {
        yield 'Test default currency' => ['default', CartPrice::TAX_STATE_GROSS, 5.0, 0.45];
        yield 'Test usd currency' => ['usd', CartPrice::TAX_STATE_GROSS, 4.0, 0.36];

        yield 'Test net default currency' => ['default', CartPrice::TAX_STATE_NET, 2.0, 0.2];
        yield 'Test net usd currency' => ['usd', CartPrice::TAX_STATE_NET, 1.0, 0.1];
    }

    public static function providerPlus(): \Generator
    {
        yield 'Test default currency' => ['default', CartPrice::TAX_STATE_GROSS, 15.0, 1.36];
        yield 'Test usd currency' => ['usd', CartPrice::TAX_STATE_GROSS, 14.0, 1.27];

        yield 'Test net default currency' => ['default', CartPrice::TAX_STATE_NET, 12.0, 1.2];
        yield 'Test net usd currency' => ['usd', CartPrice::TAX_STATE_NET, 11.0, 1.1];
    }

    public static function providerMinus(): \Generator
    {
        yield 'Test default currency' => ['default', CartPrice::TAX_STATE_GROSS, 5.0, 0.45];
        yield 'Test usd currency' => ['usd', CartPrice::TAX_STATE_GROSS, 6.0, 0.55];

        yield 'Test net default currency' => ['default', CartPrice::TAX_STATE_NET, 8.0, 0.8];
        yield 'Test net usd currency' => ['usd', CartPrice::TAX_STATE_NET, 9.0, 0.9];
    }

    private function rampUpPriceFacade(IdsCollection $ids, string $currencyKey, string $taxState): PriceFacade
    {
        $entity = new Entity();

        $quantityCalculator = new QuantityPriceCalculator(
            new GrossPriceCalculator(new TaxCalculator(), new CashRounding()),
            new NetPriceCalculator(new TaxCalculator(), new CashRounding())
        );

        $stubs = new ScriptPriceStubs(
            // not necessary for this test
            $this->createMock(Connection::class),
            $quantityCalculator,
            new PercentagePriceCalculator(new CashRounding(), $quantityCalculator, new PercentageTaxRuleBuilder()),
        );

        $original = new CalculatedPrice(10, 10, new CalculatedTaxCollection(), new TaxRuleCollection(new TaxRuleCollection([new TaxRule(10)])));

        // mock context to simulate currency and tax states
        $context = $this->createMock(SalesChannelContext::class);

        // currency key will be provided, we want to test different currencies are taking into account
        $context->expects(static::any())->method('getCurrencyId')->willReturn($ids->get($currencyKey));

        // we also want to test different tax states (gross/net)
        $context->expects(static::any())->method('getTaxState')->willReturn($taxState);
        $context->expects(static::any())->method('getItemRounding')->willReturn(new CashRoundingConfig(2, 0.01, true));

        return new PriceFacade($entity, $original, $stubs, $context);
    }
}
