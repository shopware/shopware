<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\SalesChannel\Price;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Price\CashRounding;
use Shopware\Core\Checkout\Cart\Price\GrossPriceCalculator;
use Shopware\Core\Checkout\Cart\Price\NetPriceCalculator;
use Shopware\Core\Checkout\Cart\Price\QuantityPriceCalculator;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\PriceCollection as CalculatedPriceCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRule;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Cart\Tax\TaxCalculator;
use Shopware\Core\Content\Product\Aggregate\ProductPrice\ProductPriceCollection;
use Shopware\Core\Content\Product\Aggregate\ProductPrice\ProductPriceEntity;
use Shopware\Core\Content\Product\DataAbstractionLayer\CheapestPrice\CalculatedCheapestPrice;
use Shopware\Core\Content\Product\DataAbstractionLayer\CheapestPrice\CheapestPrice;
use Shopware\Core\Content\Product\SalesChannel\Price\ProductPriceCalculator;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\PartialEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\Price;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\PriceCollection;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\Unit\UnitCollection;
use Shopware\Core\System\Unit\UnitEntity;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * @internal
 */
#[CoversClass(ProductPriceCalculator::class)]
class ProductPriceCalculatorTest extends TestCase
{
    private ProductPriceCalculator $calculator;

    protected function setUp(): void
    {
        $this->calculator = new ProductPriceCalculator(
            new StaticEntityRepository([
                new UnitCollection([(
                new UnitEntity())->assign(['id' => Defaults::CURRENCY, 'translated' => ['name' => 'test']])]),
            ]),
            new QuantityPriceCalculator(
                new GrossPriceCalculator(new TaxCalculator(), new CashRounding()),
                new NetPriceCalculator(new TaxCalculator(), new CashRounding())
            )
        );
    }

    #[DataProvider('priceWillBeCalculated')]
    public function testPriceWillBeCalculated(Entity $entity, ?PriceAssertion $expected): void
    {
        $context = $this->createMock(SalesChannelContext::class);
        $context->method('getCurrencyId')->willReturn(Defaults::CURRENCY);
        $context->method('getContext')->willReturn(Context::createDefaultContext());

        $this->calculator->calculate([$entity], $context);

        if (!$expected instanceof PriceAssertion) {
            static::assertNull($entity->get('calculatedPrice'));

            return;
        }

        $price = $entity->get('calculatedPrice');

        static::assertInstanceOf(CalculatedPrice::class, $price);

        static::assertEquals($expected->price, $price->getTotalPrice());

        static::assertEquals($expected->reference, $price->getReferencePrice()?->getPrice());

        static::assertEquals($expected->listPrice, $price->getListPrice()?->getPrice());
    }

    #[DataProvider('taxStateWillBeUsedProvider')]
    public function testTaxStateWillBeUsed(Entity $product, string $state, float $expected): void
    {
        $context = $this->createMock(SalesChannelContext::class);
        $context->method('getCurrencyId')->willReturn(Defaults::CURRENCY);
        $context->method('getContext')->willReturn(Context::createDefaultContext());
        $context->method('getTaxState')->willReturn($state);
        $context->method('buildTaxRules')->willReturn(new TaxRuleCollection([new TaxRule(10)]));
        $context->method('getItemRounding')->willreturn(new CashRoundingConfig(2, 0.01, true));

        $this->calculator->calculate([$product], $context);

        $price = $product->get('calculatedPrice');

        static::assertInstanceOf(CalculatedPrice::class, $price);

        static::assertEquals($expected, $price->getTotalPrice());
    }

    public static function taxStateWillBeUsedProvider(): \Generator
    {
        $product = (new PartialEntity())->assign([
            'taxId' => Uuid::randomHex(),
            'price' => new PriceCollection([
                new Price(Defaults::CURRENCY, 10, 20, false),
            ]),
        ]);

        yield 'Gross price will be used for gross state' => [$product, CartPrice::TAX_STATE_GROSS, 20];

        yield 'Net price will be used for net price state' => [$product, CartPrice::TAX_STATE_NET, 10];

        yield 'Net price will be used for tax free state' => [$product, CartPrice::TAX_STATE_FREE, 10];
    }

    public function testEnsureUnitCaching(): void
    {
        $reflection = new \ReflectionClass($this->calculator);
        $property = $reflection->getProperty('units');
        $property->setAccessible(true);

        static::assertNull($property->getValue($this->calculator));

        $this->calculator->calculate([], $this->createMock(SalesChannelContext::class));

        static::assertNotNull($property->getValue($this->calculator));

        // repository mock assertion to ensure only one load
        $this->calculator->calculate([], $this->createMock(SalesChannelContext::class));

        // good moment to test reset interface here
        $this->calculator->reset();
        static::assertNull($property->getValue($this->calculator));
    }

    public function testCoreServiceThrowsDecorationException(): void
    {
        $this->expectException(DecorationPatternException::class);

        (new ProductPriceCalculator(
            $this->createMock(EntityRepository::class),
            new QuantityPriceCalculator(
                new GrossPriceCalculator(new TaxCalculator(), new CashRounding()),
                new NetPriceCalculator(new TaxCalculator(), new CashRounding())
            )
        ))->getDecorated();
    }

    public static function priceWillBeCalculated(): \Generator
    {
        yield 'Price will not be calculated without tax id' => [
            new PartialEntity(),
            null,
        ];

        yield 'Price will not be calculated without price loaded' => [
            (new PartialEntity())->assign(['taxId' => Uuid::randomHex()]),
            null,
        ];

        yield 'Price will be calculated' => [
            (new PartialEntity())->assign([
                'taxId' => Uuid::randomHex(),
                'price' => new PriceCollection([
                    new Price(Defaults::CURRENCY, 1, 1, false),
                ]),
            ]),
            new PriceAssertion(1.0, null, null),
        ];

        yield 'Reference price will be calculated' => [
            (new PartialEntity())->assign([
                'taxId' => Uuid::randomHex(),
                'price' => new PriceCollection([
                    new Price(Defaults::CURRENCY, 1, 1, false),
                ]),
                'purchaseUnit' => 0.5,
                'referenceUnit' => 1,
                'unitId' => Defaults::CURRENCY,
            ]),
            new PriceAssertion(1.0, null, 2.0),
        ];

        yield 'Reference price will be not calculated, if the unit not found' => [
            (new PartialEntity())->assign([
                'taxId' => Uuid::randomHex(),
                'price' => new PriceCollection([
                    new Price(Defaults::CURRENCY, 1, 1, false),
                ]),
                'purchaseUnit' => 0.5,
                'referenceUnit' => 1,
                'unitId' => Uuid::randomHex(),
            ]),
            new PriceAssertion(1.0),
        ];

        yield 'List price will be calculated' => [
            (new PartialEntity())->assign([
                'taxId' => Uuid::randomHex(),
                'price' => new PriceCollection([
                    new Price(Defaults::CURRENCY, 1, 1, false, new Price(Defaults::CURRENCY, 2, 2, false)),
                ]),
            ]),
            new PriceAssertion(1.0, 2.0),
        ];

        yield 'Regulation price will be calculated' => [
            (new PartialEntity())->assign([
                'taxId' => Uuid::randomHex(),
                'price' => new PriceCollection([
                    new Price(Defaults::CURRENCY, 1, 1, false, null, null, new Price(Defaults::CURRENCY, 2, 2, false)),
                ]),
            ]),
            new PriceAssertion(1.0, null, null, 2.0),
        ];

        yield 'Regulation price will be skipped when equals' => [
            (new PartialEntity())->assign([
                'taxId' => Uuid::randomHex(),
                'price' => new PriceCollection([
                    new Price(Defaults::CURRENCY, 2, 2, false, null, null, new Price(Defaults::CURRENCY, 2, 2, false)),
                ]),
            ]),
            new PriceAssertion(2.0),
        ];
    }

    /**
     * @param array<int, float> $expected
     */
    #[DataProvider('advancedPricesWillBeCalculatedProvider')]
    public function testAdvancedPricesWillBeCalculated(Entity $product, array $expected): void
    {
        $context = $this->createMock(SalesChannelContext::class);
        $context->method('getCurrencyId')->willReturn(Defaults::CURRENCY);
        $context->method('getContext')->willReturn(Context::createDefaultContext());
        $context->method('getRuleIds')->willReturn([Defaults::CURRENCY]);
        $context->method('buildTaxRules')->willReturn(new TaxRuleCollection([new TaxRule(19)]));

        $this->calculator->calculate([$product], $context);

        if ($expected === []) {
            static::assertCount(0, $product->get('calculatedPrices'));

            return;
        }

        $prices = $product->get('calculatedPrices');

        static::assertInstanceOf(CalculatedPriceCollection::class, $prices);

        static::assertEquals(\count($expected), $prices->count());

        foreach ($expected as $index => $value) {
            static::assertTrue($prices->has($index));

            $price = $prices->get($index);

            static::assertEquals($value, $price->getTotalPrice());
        }
    }

    public static function advancedPricesWillBeCalculatedProvider(): \Generator
    {
        yield 'Prices will not be calculated when not loaded' => [
            (new PartialEntity())->assign(['prices' => null]),
            [],
        ];

        yield 'Only product price collection can be calculated' => [
            (new PartialEntity())->assign([
                'prices' => new EntityCollection([
                    (new ProductPriceEntity())->assign([
                        '_uniqueIdentifier' => Uuid::randomHex(),
                        'price' => new PriceCollection([
                            new Price(Defaults::CURRENCY, 1, 1, false),
                        ]),
                        'quantityStart' => 1,
                        'quantityEnd' => 2,
                    ]),
                ]),
            ]),
            [],
        ];

        yield 'Only matching rule ids will be calculated' => [
            (new PartialEntity())->assign([
                'taxId' => Uuid::randomHex(),
                'prices' => new ProductPriceCollection([
                    (new ProductPriceEntity())->assign([
                        '_uniqueIdentifier' => Uuid::randomHex(),
                        // not inside the context (see above inside mock)
                        'ruleId' => Defaults::SALES_CHANNEL_TYPE_API,
                        'price' => new PriceCollection([
                            new Price(Defaults::CURRENCY, 3, 3, false),
                        ]),
                        'quantityStart' => 1,
                        'quantityEnd' => null,
                    ]),
                ]),
            ]),
            [],
        ];

        yield 'Product will be calculated when price collection loaded' => [
            (new PartialEntity())->assign([
                'taxId' => Uuid::randomHex(),
                'prices' => new ProductPriceCollection([
                    (new ProductPriceEntity())->assign([
                        '_uniqueIdentifier' => Uuid::randomHex(),
                        'ruleId' => Defaults::CURRENCY,
                        'price' => new PriceCollection([
                            new Price(Defaults::CURRENCY, 1, 1, false),
                        ]),
                        'quantityStart' => 1,
                        'quantityEnd' => null,
                    ]),
                ]),
            ]),
            [1.0],
        ];
    }

    #[DataProvider('cheapestPriceWillBeCalculatedProvider')]
    public function testCheapestPriceWillBeCalculated(Entity $entity, ?PriceAssertion $expected): void
    {
        $context = $this->createMock(SalesChannelContext::class);
        $context->method('getCurrencyId')->willReturn(Defaults::CURRENCY);
        $context->method('getContext')->willReturn(Context::createDefaultContext());

        $this->calculator->calculate([$entity], $context);

        if (!$expected instanceof PriceAssertion) {
            static::assertNull($entity->get('calculatedCheapestPrice'));

            return;
        }

        $price = $entity->get('calculatedCheapestPrice');

        static::assertInstanceOf(CalculatedCheapestPrice::class, $price);

        static::assertEquals($expected->price, $price->getTotalPrice());

        static::assertEquals($expected->reference, $price->getReferencePrice()?->getPrice());

        static::assertEquals($expected->listPrice, $price->getListPrice()?->getPrice());
    }

    public static function cheapestPriceWillBeCalculatedProvider(): \Generator
    {
        yield 'Cheapest price calculation uses the price object' => [
            (new PartialEntity())->assign([
                'taxId' => Uuid::randomHex(),
                'price' => new PriceCollection([
                    new Price(Defaults::CURRENCY, 2, 2, false, new Price(Defaults::CURRENCY, 3, 3, false), null, new Price(Defaults::CURRENCY, 4, 4, false)),
                ]),
            ]),
            new PriceAssertion(2.0, 3.0, null, 4.0),
        ];

        yield 'Cheapest price calculation uses the cheapest price container' => [
            (new PartialEntity())->assign([
                'taxId' => Uuid::randomHex(),
                'price' => new PriceCollection([
                    new Price(Defaults::CURRENCY, 2, 2, false, new Price(Defaults::CURRENCY, 3, 3, false), null, new Price(Defaults::CURRENCY, 4, 4, false)),
                ]),
                'cheapestPrice' => (new CheapestPrice())->assign([
                    'price' => new PriceCollection([
                        new Price(Defaults::CURRENCY, 20, 20, false, new Price(Defaults::CURRENCY, 30, 30, false), null, new Price(Defaults::CURRENCY, 40, 40, false)),
                    ]),
                    'variantId' => Uuid::randomHex(),
                    'hasRange' => true,
                ]),
            ]),
            new PriceAssertion(20.0, 30.0, null, 40.0),
        ];
    }
}

/**
 * @internal
 */
class PriceAssertion
{
    public function __construct(
        public float $price,
        public ?float $listPrice = null,
        public ?float $reference = null,
        public ?float $regulation = null
    ) {
    }
}
