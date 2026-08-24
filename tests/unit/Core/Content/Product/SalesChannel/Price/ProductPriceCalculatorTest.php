<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\SalesChannel\Price;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Price\CashRounding;
use Shopware\Core\Checkout\Cart\Price\GrossPriceCalculator;
use Shopware\Core\Checkout\Cart\Price\NetPriceCalculator;
use Shopware\Core\Checkout\Cart\Price\PriceSelector;
use Shopware\Core\Checkout\Cart\Price\QuantityPriceCalculator;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\PriceCollection as CalculatedPriceCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRule;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Cart\Tax\TaxCalculator;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupEntity;
use Shopware\Core\Content\Product\Aggregate\ProductPrice\ProductPriceCollection;
use Shopware\Core\Content\Product\Aggregate\ProductPrice\ProductPriceEntity;
use Shopware\Core\Content\Product\DataAbstractionLayer\CheapestPrice\CalculatedCheapestPrice;
use Shopware\Core\Content\Product\DataAbstractionLayer\CheapestPrice\CheapestPrice;
use Shopware\Core\Content\Product\Extension\ProductPriceCalculationExtension;
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
use Shopware\Core\Framework\Extensions\ExtensionDispatcher;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Test\TestCaseHelper\CallableClass;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\Unit\UnitCollection;
use Shopware\Core\System\Unit\UnitEntity;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(ProductPriceCalculator::class)]
class ProductPriceCalculatorTest extends TestCase
{
    private ProductPriceCalculator $calculator;

    private EventDispatcher $eventDispatcher;

    protected function setUp(): void
    {
        $this->eventDispatcher = new EventDispatcher();

        $unitRepository = new StaticEntityRepository([
            new UnitCollection([(
            new UnitEntity())->assign(['id' => Defaults::CURRENCY, 'translated' => ['name' => 'test']])]),
        ]);

        $this->calculator = new ProductPriceCalculator(
            $unitRepository,
            new QuantityPriceCalculator(
                new GrossPriceCalculator(new TaxCalculator(), new CashRounding()),
                new NetPriceCalculator(new TaxCalculator(), new CashRounding())
            ),
            new ExtensionDispatcher($this->eventDispatcher),
            new PriceSelector(),
        );
    }

    public function testExtensionIsDispatched(): void
    {
        $pre = $this->createMock(CallableClass::class);
        $pre->expects($this->once())->method('__invoke');
        $this->eventDispatcher->addListener(ProductPriceCalculationExtension::NAME . '.pre', $pre);

        $post = $this->createMock(CallableClass::class);
        $post->expects($this->once())->method('__invoke');
        $this->eventDispatcher->addListener(ProductPriceCalculationExtension::NAME . '.post', $post);

        $this->calculator->calculate([], static::createStub(SalesChannelContext::class));
    }

    #[DataProvider('priceWillBeCalculated')]
    public function testPriceWillBeCalculated(Entity $entity, ?PriceAssertion $expected): void
    {
        $context = static::createStub(SalesChannelContext::class);
        $context->method('getCurrencyId')->willReturn(Defaults::CURRENCY);
        $context->method('getContext')->willReturn(Context::createDefaultContext());

        $this->calculator->calculate([$entity], $context);

        if (!$expected instanceof PriceAssertion) {
            static::assertNull($entity->get('calculatedPrice'));

            return;
        }

        $price = $entity->get('calculatedPrice');

        static::assertInstanceOf(CalculatedPrice::class, $price);

        static::assertSame($expected->price, $price->getTotalPrice());

        static::assertSame($expected->reference, $price->getReferencePrice()?->getPrice());

        static::assertSame($expected->listPrice, $price->getListPrice()?->getPrice());

        static::assertSame($expected->regulation, $price->getRegulationPrice()?->getPrice());
    }

    #[DataProvider('taxStateWillBeUsedProvider')]
    public function testTaxStateWillBeUsed(Entity $product, string $state, float $expected): void
    {
        $context = static::createStub(SalesChannelContext::class);
        $context->method('getCurrencyId')->willReturn(Defaults::CURRENCY);
        $context->method('getContext')->willReturn(Context::createDefaultContext());
        $context->method('getTaxState')->willReturn($state);
        $context->method('buildTaxRules')->willReturn(new TaxRuleCollection([new TaxRule(10)]));
        $context->method('getItemRounding')->willReturn(new CashRoundingConfig(2, 0.01, true));

        $this->calculator->calculate([$product], $context);

        $price = $product->get('calculatedPrice');

        static::assertInstanceOf(CalculatedPrice::class, $price);

        static::assertSame($expected, $price->getTotalPrice());
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

    #[DataProvider('priceBasisProvider')]
    public function testPriceBasisDecidesWhichStoredValueIsAuthoritative(string $taxState, ?string $priceBasis, float $expectedUnitPrice, float $expectedTax): void
    {
        $product = (new PartialEntity())->assign([
            'taxId' => Uuid::randomHex(),
            'price' => new PriceCollection([
                new Price(Defaults::CURRENCY, 10.0, 99.99, false),
            ]),
        ]);

        $this->createCalculator()->calculate([$product], $this->createPriceBasisContext($taxState, $priceBasis));

        $price = $product->get('calculatedPrice');

        static::assertInstanceOf(CalculatedPrice::class, $price);
        static::assertSame($expectedUnitPrice, $price->getUnitPrice());
        static::assertSame($expectedTax, $price->getCalculatedTaxes()->getAmount());
    }

    public static function priceBasisProvider(): \Generator
    {
        yield 'legacy basis takes the stored gross for gross display' => [
            CartPrice::TAX_STATE_GROSS, null, 99.99, 15.96,
        ];

        yield 'legacy basis takes the stored net for net display' => [
            CartPrice::TAX_STATE_NET, null, 10.0, 1.9,
        ];

        yield 'legacy basis takes the stored net for tax free display' => [
            CartPrice::TAX_STATE_FREE, null, 10.0, 0.0,
        ];

        yield 'net basis derives the gross from the stored net and ignores the stored gross' => [
            CartPrice::TAX_STATE_GROSS, CustomerGroupEntity::PRICE_BASIS_NET, 11.9, 1.9,
        ];

        yield 'net basis takes the stored net verbatim for net display' => [
            CartPrice::TAX_STATE_NET, CustomerGroupEntity::PRICE_BASIS_NET, 10.0, 1.9,
        ];

        yield 'net basis takes the stored net verbatim for tax free display' => [
            CartPrice::TAX_STATE_FREE, CustomerGroupEntity::PRICE_BASIS_NET, 10.0, 0.0,
        ];
    }

    #[DataProvider('foreignCurrencyProvider')]
    public function testFallbackToTheDefaultCurrencyPriceAppliesTheCurrencyFactor(?string $priceBasis, float $expectedUnitPrice, float $expectedTax): void
    {
        $product = (new PartialEntity())->assign([
            'taxId' => Uuid::randomHex(),
            'price' => new PriceCollection([
                new Price(Defaults::CURRENCY, 10.0, 20.0, false),
            ]),
        ]);

        $context = $this->createPriceBasisContext(
            CartPrice::TAX_STATE_GROSS,
            $priceBasis,
            currencyId: Uuid::randomHex(),
            currencyFactor: 1.5
        );

        $this->createCalculator()->calculate([$product], $context);

        $price = $product->get('calculatedPrice');

        static::assertInstanceOf(CalculatedPrice::class, $price);
        static::assertSame($expectedUnitPrice, $price->getUnitPrice());
        static::assertSame($expectedTax, $price->getCalculatedTaxes()->getAmount());
    }

    public static function foreignCurrencyProvider(): \Generator
    {
        yield 'legacy basis converts the stored gross with the currency factor' => [null, 30.0, 4.79];

        yield 'net basis converts the stored net and derives the gross afterwards' => [
            CustomerGroupEntity::PRICE_BASIS_NET, 17.85, 2.85,
        ];
    }

    public function testNetPriceBasisDerivesListAndRegulationPrices(): void
    {
        $product = (new PartialEntity())->assign([
            'taxId' => Uuid::randomHex(),
            'price' => new PriceCollection([
                new Price(
                    currencyId: Defaults::CURRENCY,
                    net: 10.0,
                    gross: 99.99,
                    linked: false,
                    listPrice: new Price(Defaults::CURRENCY, 20.0, 199.99, false),
                    regulationPrice: new Price(Defaults::CURRENCY, 15.0, 149.99, false)
                ),
            ]),
        ]);

        $context = $this->createPriceBasisContext(CartPrice::TAX_STATE_GROSS, CustomerGroupEntity::PRICE_BASIS_NET);

        $this->createCalculator()->calculate([$product], $context);

        $price = $product->get('calculatedPrice');

        static::assertInstanceOf(CalculatedPrice::class, $price);
        static::assertSame(23.8, $price->getListPrice()?->getPrice());
        static::assertSame(17.85, $price->getRegulationPrice()?->getPrice());
    }

    public function testNetPriceBasisSkipsTheRegulationPriceWhenTheNetValueIsZero(): void
    {
        $product = (new PartialEntity())->assign([
            'taxId' => Uuid::randomHex(),
            'price' => new PriceCollection([
                new Price(
                    currencyId: Defaults::CURRENCY,
                    net: 0.0,
                    gross: 99.99,
                    linked: false,
                    regulationPrice: new Price(Defaults::CURRENCY, 15.0, 149.99, false)
                ),
            ]),
        ]);

        $context = $this->createPriceBasisContext(CartPrice::TAX_STATE_GROSS, CustomerGroupEntity::PRICE_BASIS_NET);

        $this->createCalculator()->calculate([$product], $context);

        $price = $product->get('calculatedPrice');

        static::assertInstanceOf(CalculatedPrice::class, $price);
        static::assertNull($price->getRegulationPrice());
    }

    public function testNetPriceBasisAppliesToAdvancedAndCheapestPrices(): void
    {
        $product = (new PartialEntity())->assign([
            'id' => Uuid::randomHex(),
            'taxId' => Uuid::randomHex(),
            'prices' => new ProductPriceCollection([
                (new ProductPriceEntity())->assign([
                    'id' => Uuid::randomHex(),
                    '_uniqueIdentifier' => Uuid::randomHex(),
                    'ruleId' => Defaults::CURRENCY,
                    'price' => new PriceCollection([new Price(Defaults::CURRENCY, 10.0, 99.99, false)]),
                    'quantityStart' => 1,
                    'quantityEnd' => null,
                ]),
            ]),
            'cheapestPrice' => (new CheapestPrice())->assign([
                'price' => new PriceCollection([new Price(Defaults::CURRENCY, 10.0, 99.99, false)]),
                'variantId' => Uuid::randomHex(),
                'hasRange' => false,
            ]),
        ]);

        $context = $this->createPriceBasisContext(CartPrice::TAX_STATE_GROSS, CustomerGroupEntity::PRICE_BASIS_NET);
        $context->method('getRuleIds')->willReturn([Defaults::CURRENCY]);

        $this->createCalculator()->calculate([$product], $context);

        $prices = $product->get('calculatedPrices');
        static::assertInstanceOf(CalculatedPriceCollection::class, $prices);
        static::assertSame(11.9, $prices->first()?->getUnitPrice());

        $cheapest = $product->get('calculatedCheapestPrice');
        static::assertInstanceOf(CalculatedCheapestPrice::class, $cheapest);
        static::assertSame(11.9, $cheapest->getUnitPrice());
    }

    public function testEnsureUnitCaching(): void
    {
        $property = new \ReflectionProperty(ProductPriceCalculator::class, 'units');

        static::assertNull($property->getValue($this->calculator));

        $this->calculator->calculate([], static::createStub(SalesChannelContext::class));

        static::assertNotNull($property->getValue($this->calculator));

        // repository mock assertion to ensure only one load
        $this->calculator->calculate([], static::createStub(SalesChannelContext::class));

        // good moment to test reset interface here
        $this->calculator->reset();
        static::assertNull($property->getValue($this->calculator));
    }

    public function testCoreServiceThrowsDecorationException(): void
    {
        $this->expectException(DecorationPatternException::class);

        (new ProductPriceCalculator(
            static::createStub(EntityRepository::class),
            new QuantityPriceCalculator(
                new GrossPriceCalculator(new TaxCalculator(), new CashRounding()),
                new NetPriceCalculator(new TaxCalculator(), new CashRounding())
            ),
            new ExtensionDispatcher($this->eventDispatcher),
            new PriceSelector()
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

        yield 'Regulation price will be not skipped when equals' => [
            (new PartialEntity())->assign([
                'taxId' => Uuid::randomHex(),
                'price' => new PriceCollection([
                    new Price(
                        currencyId: Defaults::CURRENCY,
                        net: 2,
                        gross: 2,
                        linked: false,
                        regulationPrice: new Price(Defaults::CURRENCY, 2, 2, false)
                    ),
                ]),
            ]),
            new PriceAssertion(2.0, null, null, 2.0),
        ];
    }

    /**
     * @param array<int, float> $expected
     */
    #[DataProvider('advancedPricesWillBeCalculatedProvider')]
    public function testAdvancedPricesWillBeCalculated(Entity $product, array $expected): void
    {
        $context = static::createStub(SalesChannelContext::class);
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

        static::assertCount(\count($expected), $prices);

        foreach ($expected as $index => $value) {
            static::assertTrue($prices->has($index));

            $price = $prices->get($index);

            static::assertSame($value, $price->getTotalPrice());
        }
    }

    public function testFilterRulePricesReturnsFirstMatchingContextRuleInOrder(): void
    {
        $ruleA = Uuid::randomHex();
        $ruleB = Uuid::randomHex();

        $context = static::createStub(SalesChannelContext::class);
        $context->method('getCurrencyId')->willReturn(Defaults::CURRENCY);
        $context->method('getContext')->willReturn(Context::createDefaultContext());
        // ruleA takes precedence because it is listed first in the context rule ids
        $context->method('getRuleIds')->willReturn([$ruleA, $ruleB]);
        $context->method('buildTaxRules')->willReturn(new TaxRuleCollection([new TaxRule(19)]));

        $product = (new PartialEntity())->assign([
            'id' => Uuid::randomHex(),
            'taxId' => Uuid::randomHex(),
            'prices' => new ProductPriceCollection([
                (new ProductPriceEntity())->assign([
                    'id' => Uuid::randomHex(),
                    '_uniqueIdentifier' => Uuid::randomHex(),
                    'ruleId' => $ruleB,
                    'price' => new PriceCollection([new Price(Defaults::CURRENCY, 5, 5, false)]),
                    'quantityStart' => 1,
                    'quantityEnd' => null,
                ]),
                (new ProductPriceEntity())->assign([
                    'id' => Uuid::randomHex(),
                    '_uniqueIdentifier' => Uuid::randomHex(),
                    'ruleId' => $ruleA,
                    'price' => new PriceCollection([new Price(Defaults::CURRENCY, 1, 1, false)]),
                    'quantityStart' => 1,
                    'quantityEnd' => null,
                ]),
            ]),
        ]);

        $this->calculator->calculate([$product], $context);

        $prices = $product->get('calculatedPrices');

        static::assertInstanceOf(CalculatedPriceCollection::class, $prices);
        static::assertCount(1, $prices);
        // 1.0 proves ruleA won; the regression would pick ruleB (5.0)
        static::assertSame(1.0, $prices->first()?->getTotalPrice());
    }

    public function testFilterRulePricesSkipsContextRulesWithoutMatchingPrices(): void
    {
        $ruleWithoutPrices = Uuid::randomHex();
        $ruleWithPrices = Uuid::randomHex();

        $context = static::createStub(SalesChannelContext::class);
        $context->method('getCurrencyId')->willReturn(Defaults::CURRENCY);
        $context->method('getContext')->willReturn(Context::createDefaultContext());
        // the first context rule has no price, the calculator must fall through to the next
        $context->method('getRuleIds')->willReturn([$ruleWithoutPrices, $ruleWithPrices]);
        $context->method('buildTaxRules')->willReturn(new TaxRuleCollection([new TaxRule(19)]));

        $product = (new PartialEntity())->assign([
            'id' => Uuid::randomHex(),
            'taxId' => Uuid::randomHex(),
            'prices' => new ProductPriceCollection([
                (new ProductPriceEntity())->assign([
                    'id' => Uuid::randomHex(),
                    '_uniqueIdentifier' => Uuid::randomHex(),
                    'ruleId' => $ruleWithPrices,
                    'price' => new PriceCollection([new Price(Defaults::CURRENCY, 1, 1, false)]),
                    'quantityStart' => 1,
                    'quantityEnd' => null,
                ]),
            ]),
        ]);

        $this->calculator->calculate([$product], $context);

        $prices = $product->get('calculatedPrices');

        static::assertInstanceOf(CalculatedPriceCollection::class, $prices);
        static::assertCount(1, $prices);
        static::assertSame(1.0, $prices->first()?->getTotalPrice());
    }

    public static function advancedPricesWillBeCalculatedProvider(): \Generator
    {
        yield 'Prices will not be calculated when not loaded' => [
            (new PartialEntity())->assign(['id' => Uuid::randomHex(), 'prices' => null]),
            [],
        ];

        yield 'Partial entity price collection will be calculated' => [
            (new PartialEntity())->assign([
                'id' => Uuid::randomHex(),
                'taxId' => Uuid::randomHex(),
                'prices' => new EntityCollection([
                    (new PartialEntity())->assign([
                        'id' => Uuid::randomHex(),
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

        yield 'Only matching rule ids will be calculated' => [
            (new PartialEntity())->assign([
                'id' => Uuid::randomHex(),
                'taxId' => Uuid::randomHex(),
                'prices' => new ProductPriceCollection([
                    (new ProductPriceEntity())->assign([
                        'id' => Uuid::randomHex(),
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
                'id' => Uuid::randomHex(),
                'taxId' => Uuid::randomHex(),
                'prices' => new ProductPriceCollection([
                    (new ProductPriceEntity())->assign([
                        'id' => Uuid::randomHex(),
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
        $context = static::createStub(SalesChannelContext::class);
        $context->method('getCurrencyId')->willReturn(Defaults::CURRENCY);
        $context->method('getContext')->willReturn(Context::createDefaultContext());

        $this->calculator->calculate([$entity], $context);

        if (!$expected instanceof PriceAssertion) {
            static::assertNull($entity->get('calculatedCheapestPrice'));

            return;
        }

        $price = $entity->get('calculatedCheapestPrice');

        static::assertInstanceOf(CalculatedCheapestPrice::class, $price);

        static::assertSame($expected->price, $price->getTotalPrice());

        static::assertSame($expected->reference, $price->getReferencePrice()?->getPrice());

        static::assertSame($expected->listPrice, $price->getListPrice()?->getPrice());
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

    private function createCalculator(): ProductPriceCalculator
    {
        return new ProductPriceCalculator(
            new StaticEntityRepository([new UnitCollection()]),
            new QuantityPriceCalculator(
                new GrossPriceCalculator(new TaxCalculator(), new CashRounding()),
                new NetPriceCalculator(new TaxCalculator(), new CashRounding())
            ),
            new ExtensionDispatcher($this->eventDispatcher),
            new PriceSelector(),
        );
    }

    private function createPriceBasisContext(string $taxState, ?string $priceBasis, string $currencyId = Defaults::CURRENCY, float $currencyFactor = 1.0): SalesChannelContext&Stub
    {
        $baseContext = Context::createDefaultContext();
        $baseContext->assign(['currencyFactor' => $currencyFactor]);

        $context = static::createStub(SalesChannelContext::class);
        $context->method('getCurrencyId')->willReturn($currencyId);
        $context->method('getContext')->willReturn($baseContext);
        $context->method('getTaxState')->willReturn($taxState);
        $context->method('buildTaxRules')->willReturn(new TaxRuleCollection([new TaxRule(19)]));
        $context->method('getItemRounding')->willReturn(new CashRoundingConfig(2, 0.01, true));
        $context->method('getCurrentCustomerGroup')->willReturn(
            (new CustomerGroupEntity())->assign(['priceBasis' => $priceBasis])
        );

        return $context;
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
