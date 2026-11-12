<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Delivery;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\Delivery\DeliveryBuilder;
use Shopware\Core\Checkout\Cart\Delivery\DeliveryCalculator;
use Shopware\Core\Checkout\Cart\Delivery\DeliveryProcessor;
use Shopware\Core\Checkout\Cart\Delivery\DeliveryRuleMatcher;
use Shopware\Core\Checkout\Cart\Delivery\Struct\Delivery;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryCollection;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryDate;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryPositionCollection;
use Shopware\Core\Checkout\Cart\Delivery\Struct\ShippingLocation;
use Shopware\Core\Checkout\Cart\LineItem\CartDataCollection;
use Shopware\Core\Checkout\Cart\Order\IdStruct;
use Shopware\Core\Checkout\Cart\Order\OrderConverter;
use Shopware\Core\Checkout\Cart\Price\CashRounding;
use Shopware\Core\Checkout\Cart\Price\GrossPriceCalculator;
use Shopware\Core\Checkout\Cart\Price\NetPriceCalculator;
use Shopware\Core\Checkout\Cart\Price\PriceSelector;
use Shopware\Core\Checkout\Cart\Price\QuantityPriceCalculator;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Tax\PercentageTaxRuleBuilder;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRule;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Cart\Tax\TaxCalculator;
use Shopware\Core\Checkout\CheckoutPermissions;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Extensions\ExtensionDispatcher;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Annotation\DisabledFeatures;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(DeliveryProcessor::class)]
class DeliveryProcessorTest extends TestCase
{
    public function testCollectShippingMethods(): void
    {
        $shippingMethod = new ShippingMethodEntity();
        $shippingMethod->setId(Uuid::randomHex());

        $context = $this->createMock(SalesChannelContext::class);
        $context
            ->expects($this->once())
            ->method('getShippingMethod')
            ->willReturn($shippingMethod);

        $result = $this->createMock(EntitySearchResult::class);
        $result
            ->expects($this->once())
            ->method('getEntities')->willReturn(new EntityCollection([$shippingMethod]));

        $repository = $this->createMock(EntityRepository::class);
        $repository
            ->expects($this->once())
            ->method('search')->willReturn($result);

        $processor = new DeliveryProcessor(
            static::createStub(DeliveryBuilder::class),
            static::createStub(DeliveryCalculator::class),
            $repository,
            static::createStub(DeliveryRuleMatcher::class)
        );

        $data = new CartDataCollection();
        $processor->collect($data, new Cart('test'), $context, new CartBehavior());

        static::assertInstanceOf(ShippingMethodEntity::class, $data->get($processor::buildKey($shippingMethod->getId())));
    }

    public function testProcessDeliveryCost(): void
    {
        $context = static::createStub(SalesChannelContext::class);
        $calculator = $this->createMock(DeliveryCalculator::class);
        $calculator
            ->expects($this->once())
            ->method('calculate');

        $delivery = new Delivery(
            new DeliveryPositionCollection(),
            new DeliveryDate(new \DateTime(), new \DateTime()),
            new ShippingMethodEntity(),
            new ShippingLocation(new CountryEntity(), null, null),
            new CalculatedPrice(0.0, 0.0, new CalculatedTaxCollection(), new TaxRuleCollection()),
        );

        $builder = $this->createMock(DeliveryBuilder::class);
        $builder
            ->expects($this->once())
            ->method('build')
            ->willReturn(new DeliveryCollection([$delivery]));

        $processor = new DeliveryProcessor($builder, $calculator, static::createStub(EntityRepository::class), static::createStub(DeliveryRuleMatcher::class));

        $manualShippingCosts = new CalculatedPrice(10.00, 10.0, new CalculatedTaxCollection(), new TaxRuleCollection());
        $original = new Cart('test');
        $original->addExtension(DeliveryProcessor::MANUAL_SHIPPING_COSTS, $manualShippingCosts);

        $toCalculate = new Cart('calculate');
        $processor->process(new CartDataCollection(), $original, $toCalculate, $context, new CartBehavior());

        // the processor applied the manual shipping costs to the built delivery
        static::assertSame($manualShippingCosts, $delivery->getShippingCosts());
        static::assertNotCount(0, $toCalculate->getDeliveries());
    }

    public function testProcessMatchesRulesPerDeliveryAgainstThePreviouslyCalculatedCartBeforeCalculatingTheCosts(): void
    {
        $context = static::createStub(SalesChannelContext::class);
        $data = new CartDataCollection();
        $original = new Cart('test');
        $toCalculate = new Cart('calculate');

        $delivery = $this->createDelivery();
        $deliveries = new DeliveryCollection([$delivery]);

        $builder = static::createStub(DeliveryBuilder::class);
        $builder->method('build')->willReturn($deliveries);

        $matcher = $this->createMock(DeliveryRuleMatcher::class);
        $matcher
            ->expects($this->once())
            ->method('match')
            ->with($data, $original, $deliveries, $context)
            ->willReturnCallback(static fn () => $delivery->setRuleIds(['matched-rule-id']));

        $calculator = $this->createMock(DeliveryCalculator::class);
        $calculator
            ->expects($this->once())
            ->method('calculate')
            ->with($data, $toCalculate, $deliveries, $context)
            ->willReturnCallback(static function (CartDataCollection $data, Cart $cart, DeliveryCollection $deliveries): void {
                static::assertSame(['matched-rule-id'], $deliveries->first()?->getRuleIds());
            });

        $processor = new DeliveryProcessor($builder, $calculator, static::createStub(EntityRepository::class), $matcher);
        $processor->process($data, $original, $toCalculate, $context, new CartBehavior());

        static::assertSame($deliveries, $toCalculate->getDeliveries());
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testProcessDoesNotMatchRulesPerDeliveryWithoutTheMajorFlag(): void
    {
        $delivery = $this->createDelivery();

        $builder = static::createStub(DeliveryBuilder::class);
        $builder->method('build')->willReturn(new DeliveryCollection([$delivery]));

        $matcher = $this->createMock(DeliveryRuleMatcher::class);
        $matcher->expects($this->never())->method('match');

        $calculator = $this->createMock(DeliveryCalculator::class);
        $calculator->expects($this->once())->method('calculate');

        $processor = new DeliveryProcessor($builder, $calculator, static::createStub(EntityRepository::class), $matcher);
        $processor->process(new CartDataCollection(), new Cart('test'), new Cart('calculate'), static::createStub(SalesChannelContext::class), new CartBehavior());

        static::assertNull($delivery->getRuleIds());
    }

    public function testProcessKeepsTheOriginalDeliveriesUnmatchedWhenThePriceRecalculationIsSkipped(): void
    {
        $delivery = $this->createDelivery();
        $original = new Cart('test');
        $original->setDeliveries(new DeliveryCollection([$delivery]));

        $builder = $this->createMock(DeliveryBuilder::class);
        $builder->expects($this->never())->method('build');

        $matcher = $this->createMock(DeliveryRuleMatcher::class);
        $matcher->expects($this->never())->method('match');

        $calculator = $this->createMock(DeliveryCalculator::class);
        $calculator->expects($this->once())->method('calculate');

        $toCalculate = new Cart('calculate');
        $behavior = new CartBehavior([CheckoutPermissions::SKIP_DELIVERY_PRICE_RECALCULATION => true]);

        $processor = new DeliveryProcessor($builder, $calculator, static::createStub(EntityRepository::class), $matcher);
        $processor->process(new CartDataCollection(), $original, $toCalculate, static::createStub(SalesChannelContext::class), $behavior);

        static::assertSame($delivery, $toCalculate->getDeliveries()->first());
        static::assertNull($delivery->getRuleIds());
    }

    public function testManualShippingCostsOnlyApplyToThePrimaryDeliveryOnRecalculation(): void
    {
        $primaryDelivery = $this->createOrderDelivery('primary-delivery', 0.0);
        $secondDelivery = $this->createOrderDelivery('second-delivery', 4.99);

        $original = new Cart('test');
        $original->setDeliveries(new DeliveryCollection([$primaryDelivery, $secondDelivery]));
        $original->addExtension(OrderConverter::ORIGINAL_PRIMARY_ORDER_DELIVERY, new IdStruct('primary-delivery'));

        $behavior = new CartBehavior([CheckoutPermissions::SKIP_DELIVERY_PRICE_RECALCULATION => true]);
        $toCalculate = new Cart('test');
        $toCalculate->setBehavior($behavior);
        $toCalculate->addExtension(
            DeliveryProcessor::MANUAL_SHIPPING_COSTS,
            new CalculatedPrice(7.0, 7.0, new CalculatedTaxCollection(), new TaxRuleCollection())
        );

        $context = static::createStub(SalesChannelContext::class);
        $context->method('getTaxState')->willReturn(CartPrice::TAX_STATE_GROSS);
        $context->method('getItemRounding')->willReturn(new CashRoundingConfig(decimals: 2, interval: 0.01, roundForNet: true));
        $context->method('buildTaxRules')->willReturn(new TaxRuleCollection([new TaxRule(19)]));

        $processor = new DeliveryProcessor(
            static::createStub(DeliveryBuilder::class),
            $this->createRealDeliveryCalculator(),
            static::createStub(EntityRepository::class),
            static::createStub(DeliveryRuleMatcher::class)
        );
        $processor->process(new CartDataCollection(), $original, $toCalculate, $context, $behavior);

        static::assertCount(2, $toCalculate->getDeliveries());
        static::assertSame(7.0, $primaryDelivery->getShippingCosts()->getTotalPrice());
        static::assertSame(4.99, $secondDelivery->getShippingCosts()->getTotalPrice());
    }

    /**
     * @param list<int|null> $builtPositions
     * @param list<int> $expectedPositions
     */
    #[DataProvider('builtDeliveryPositionProvider')]
    public function testProcessNumbersTheBuiltDeliveriesBeforeCalculatingTheCosts(array $builtPositions, array $expectedPositions): void
    {
        $deliveries = new DeliveryCollection(array_map($this->createDeliveryAtPosition(...), $builtPositions));

        $builder = static::createStub(DeliveryBuilder::class);
        $builder->method('build')->willReturn($deliveries);

        $calculator = $this->createMock(DeliveryCalculator::class);
        $calculator
            ->expects($this->once())
            ->method('calculate')
            ->willReturnCallback(static function (CartDataCollection $data, Cart $cart, DeliveryCollection $deliveries) use ($expectedPositions): void {
                static::assertSame($expectedPositions, self::positionsOf($deliveries));
            });

        $processor = new DeliveryProcessor($builder, $calculator, static::createStub(EntityRepository::class), static::createStub(DeliveryRuleMatcher::class));
        $processor->process(new CartDataCollection(), new Cart('test'), new Cart('calculate'), static::createStub(SalesChannelContext::class), new CartBehavior());
    }

    public static function builtDeliveryPositionProvider(): \Generator
    {
        yield 'a fresh cart is numbered in cart order' => [[null, null, null], [1, 2, 3]];
        yield 'positions set by the builder are kept' => [[2, null, 1], [2, 3, 1]];
    }

    /**
     * @param list<int|null> $originalPositions
     * @param list<int> $expectedPositions
     */
    #[DataProvider('recalculatedDeliveryPositionProvider')]
    public function testProcessKeepsTheStoredPositionsWhenThePriceRecalculationIsSkipped(array $originalPositions, array $expectedPositions): void
    {
        $original = new Cart('test');
        $original->setDeliveries(new DeliveryCollection(array_map($this->createDeliveryAtPosition(...), $originalPositions)));

        $calculator = $this->createMock(DeliveryCalculator::class);
        $calculator
            ->expects($this->once())
            ->method('calculate')
            ->willReturnCallback(static function (CartDataCollection $data, Cart $cart, DeliveryCollection $deliveries) use ($expectedPositions): void {
                static::assertSame($expectedPositions, self::positionsOf($deliveries));
            });

        $behavior = new CartBehavior([CheckoutPermissions::SKIP_DELIVERY_PRICE_RECALCULATION => true]);

        $processor = new DeliveryProcessor(static::createStub(DeliveryBuilder::class), $calculator, static::createStub(EntityRepository::class), static::createStub(DeliveryRuleMatcher::class));
        $processor->process(new CartDataCollection(), $original, new Cart('calculate'), static::createStub(SalesChannelContext::class), $behavior);
    }

    public static function recalculatedDeliveryPositionProvider(): \Generator
    {
        yield 'stored positions stay with their deliveries' => [[2, 1], [2, 1]];
        yield 'an order placed before deliveries had positions is numbered in cart order' => [[null, null], [1, 2]];
    }

    /**
     * @return list<int|null>
     */
    private static function positionsOf(DeliveryCollection $deliveries): array
    {
        return array_values($deliveries->map(static fn (Delivery $delivery) => $delivery->getPosition()));
    }

    private function createDeliveryAtPosition(?int $position): Delivery
    {
        $delivery = $this->createDelivery();
        $delivery->setPosition($position);

        return $delivery;
    }

    private function createDelivery(): Delivery
    {
        return new Delivery(
            new DeliveryPositionCollection(),
            new DeliveryDate(new \DateTime(), new \DateTime()),
            new ShippingMethodEntity(),
            new ShippingLocation(new CountryEntity(), null, null),
            new CalculatedPrice(0.0, 0.0, new CalculatedTaxCollection(), new TaxRuleCollection()),
        );
    }

    private function createOrderDelivery(string $orderDeliveryId, float $shippingCosts): Delivery
    {
        $shippingMethod = new ShippingMethodEntity();
        $shippingMethod->setId(Uuid::randomHex());
        $shippingMethod->setTaxType(ShippingMethodEntity::TAX_TYPE_FIXED);
        $shippingMethod->setTaxId(Uuid::randomHex());

        $delivery = new Delivery(
            new DeliveryPositionCollection(),
            new DeliveryDate(new \DateTime(), new \DateTime()),
            $shippingMethod,
            new ShippingLocation(new CountryEntity(), null, null),
            new CalculatedPrice($shippingCosts, $shippingCosts, new CalculatedTaxCollection(), new TaxRuleCollection()),
        );
        $delivery->addExtension(OrderConverter::ORIGINAL_ID, new IdStruct($orderDeliveryId));

        return $delivery;
    }

    private function createRealDeliveryCalculator(): DeliveryCalculator
    {
        return new DeliveryCalculator(
            new QuantityPriceCalculator(
                new GrossPriceCalculator(new TaxCalculator(), new CashRounding()),
                new NetPriceCalculator(new TaxCalculator(), new CashRounding())
            ),
            new PercentageTaxRuleBuilder(),
            new CashRounding(),
            new PriceSelector(new TaxCalculator(), new ExtensionDispatcher(new EventDispatcher())),
        );
    }
}
