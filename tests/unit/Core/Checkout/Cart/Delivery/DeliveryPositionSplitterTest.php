<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Delivery;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\CartException;
use Shopware\Core\Checkout\Cart\Delivery\DeliveryBuilder;
use Shopware\Core\Checkout\Cart\Delivery\DeliveryPositionSplitter;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryDate;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryInformation;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryPosition;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryTime;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\LineItem\LineItemQuantitySplitter;
use Shopware\Core\Checkout\Cart\Order\IdStruct;
use Shopware\Core\Checkout\Cart\Order\OrderConverter;
use Shopware\Core\Checkout\Cart\Price\CashRounding;
use Shopware\Core\Checkout\Cart\Price\GrossPriceCalculator;
use Shopware\Core\Checkout\Cart\Price\NetPriceCalculator;
use Shopware\Core\Checkout\Cart\Price\QuantityPriceCalculator;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRule;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Cart\Tax\TaxCalculator;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\DeliveryTime\DeliveryTimeEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Annotation\DisabledFeatures;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(DeliveryPositionSplitter::class)]
class DeliveryPositionSplitterTest extends TestCase
{
    use ClockSensitiveTrait;

    private QuantityPriceCalculator $quantityPriceCalculator;

    private DeliveryPositionSplitter $splitter;

    protected function setUp(): void
    {
        self::mockTime('2026-11-05 10:00:00');

        $taxCalculator = new TaxCalculator();
        $cashRounding = new CashRounding();
        $this->quantityPriceCalculator = new QuantityPriceCalculator(
            new GrossPriceCalculator($taxCalculator, $cashRounding),
            new NetPriceCalculator($taxCalculator, $cashRounding),
        );

        $this->splitter = new DeliveryPositionSplitter(
            new LineItemQuantitySplitter($this->quantityPriceCalculator),
            new DeliveryBuilder(),
        );
    }

    public function testSplitsGrossPositionIntoPartAndRemainder(): void
    {
        $context = $this->createContext(CartPrice::TAX_STATE_GROSS);
        $position = $this->createPosition($this->createLineItem(quantity: 3, unitPrice: 9.99, taxRules: new TaxRuleCollection([new TaxRule(19)]), context: $context));
        static::assertSame([[19.0, 4.79]], self::taxes($position));

        [$part, $remainder] = $this->splitter->split($position, 2, $context);

        static::assertSame('line-item-id', $part->getIdentifier());
        static::assertSame(2, $part->getQuantity());
        static::assertSame(2, $part->getLineItem()->getQuantity());
        static::assertSame(19.98, $part->getPrice()->getTotalPrice());
        static::assertEqualsWithDelta([[19.0, 4.79 / 3 * 2]], self::taxes($part), 1e-9);
        static::assertEquals($part->getLineItem()->getPrice(), $part->getPrice());

        static::assertSame('line-item-id', $remainder->getIdentifier());
        static::assertSame(1, $remainder->getQuantity());
        static::assertSame(1, $remainder->getLineItem()->getQuantity());
        static::assertSame(9.99, $remainder->getPrice()->getTotalPrice());
        static::assertEqualsWithDelta([[19.0, 4.79 / 3]], self::taxes($remainder), 1e-9);
        static::assertEquals($remainder->getLineItem()->getPrice(), $remainder->getPrice());

        static::assertSame(3, $position->getLineItem()->getQuantity());
        static::assertSame(29.97, $position->getPrice()->getTotalPrice());
    }

    public function testSplitsNetPosition(): void
    {
        $context = $this->createContext(CartPrice::TAX_STATE_NET);
        $position = $this->createPosition($this->createLineItem(quantity: 3, unitPrice: 9.99, taxRules: new TaxRuleCollection([new TaxRule(19)]), context: $context));
        static::assertSame([[19.0, 5.69]], self::taxes($position));

        [$part, $remainder] = $this->splitter->split($position, 2, $context);

        static::assertSame(19.98, $part->getPrice()->getTotalPrice());
        static::assertEqualsWithDelta([[19.0, 5.69 / 3 * 2]], self::taxes($part), 1e-9);
        static::assertSame(9.99, $remainder->getPrice()->getTotalPrice());
        static::assertEqualsWithDelta([[19.0, 5.69 / 3]], self::taxes($remainder), 1e-9);
    }

    public function testSplitsTaxFreePosition(): void
    {
        $context = $this->createContext(CartPrice::TAX_STATE_FREE);
        $position = $this->createPosition($this->createLineItem(quantity: 3, unitPrice: 9.99, taxRules: new TaxRuleCollection([new TaxRule(19)]), context: $context));

        [$part, $remainder] = $this->splitter->split($position, 2, $context);

        static::assertSame(19.98, $part->getPrice()->getTotalPrice());
        static::assertSame([], self::taxes($part));
        static::assertCount(0, $part->getPrice()->getTaxRules());
        static::assertSame(9.99, $remainder->getPrice()->getTotalPrice());
        static::assertSame([], self::taxes($remainder));
        static::assertCount(0, $remainder->getPrice()->getTaxRules());
    }

    public function testSplitsPositionWithMixedTaxRates(): void
    {
        $context = $this->createContext(CartPrice::TAX_STATE_GROSS);
        $taxRules = new TaxRuleCollection([new TaxRule(19, 50), new TaxRule(7, 50)]);
        $position = $this->createPosition($this->createLineItem(quantity: 3, unitPrice: 10.0, taxRules: $taxRules, context: $context));
        static::assertSame([[19.0, 2.39], [7.0, 0.98]], self::taxes($position));

        [$part, $remainder] = $this->splitter->split($position, 2, $context);

        static::assertSame(20.0, $part->getPrice()->getTotalPrice());
        static::assertEqualsWithDelta([[19.0, 2.39 / 3 * 2], [7.0, 0.98 / 3 * 2]], self::taxes($part), 1e-9);
        static::assertSame(10.0, $remainder->getPrice()->getTotalPrice());
        static::assertEqualsWithDelta([[19.0, 2.39 / 3], [7.0, 0.98 / 3]], self::taxes($remainder), 1e-9);
    }

    public function testPartTaxesAddUpToThePositionTaxes(): void
    {
        $context = $this->createContext(CartPrice::TAX_STATE_GROSS);
        $position = $this->createPosition($this->createLineItem(quantity: 3, unitPrice: 0.99, taxRules: new TaxRuleCollection([new TaxRule(19)]), context: $context));

        [$part, $remainder] = $this->splitter->split($position, 2, $context);

        static::assertSame(1.98, $part->getPrice()->getTotalPrice());
        static::assertSame(0.99, $remainder->getPrice()->getTotalPrice());
        static::assertEqualsWithDelta(
            $position->getPrice()->getCalculatedTaxes()->getAmount(),
            $part->getPrice()->getCalculatedTaxes()->getAmount() + $remainder->getPrice()->getCalculatedTaxes()->getAmount(),
            1e-9,
        );
    }

    /**
     * @deprecated tag:v6.8.0 - Remove with the PROPORTIONAL_CART_TAXES flag
     */
    #[DisabledFeatures(['PROPORTIONAL_CART_TAXES'])]
    public function testRecalculatedPartTaxesCanDifferFromThePositionByRounding(): void
    {
        $context = $this->createContext(CartPrice::TAX_STATE_GROSS);
        $position = $this->createPosition($this->createLineItem(quantity: 3, unitPrice: 0.99, taxRules: new TaxRuleCollection([new TaxRule(19)]), context: $context));
        static::assertSame([[19.0, 0.47]], self::taxes($position));

        [$part, $remainder] = $this->splitter->split($position, 2, $context);

        static::assertSame(1.98, $part->getPrice()->getTotalPrice());
        static::assertSame([[19.0, 0.32]], self::taxes($part));
        static::assertSame(0.99, $remainder->getPrice()->getTotalPrice());
        static::assertSame([[19.0, 0.16]], self::taxes($remainder));
    }

    public function testOnlyTheRemainderKeepsThePositionExtensions(): void
    {
        $context = $this->createContext(CartPrice::TAX_STATE_GROSS);
        $position = $this->createPosition($this->createLineItem(quantity: 3, unitPrice: 10.0, taxRules: new TaxRuleCollection([new TaxRule(19)]), context: $context));
        $position->addExtension(OrderConverter::ORIGINAL_ID, new IdStruct('order-delivery-position-id'));

        [$part, $remainder] = $this->splitter->split($position, 2, $context);

        static::assertFalse($part->hasExtension(OrderConverter::ORIGINAL_ID));
        static::assertSame('order-delivery-position-id', $remainder->getExtensionOfType(OrderConverter::ORIGINAL_ID, IdStruct::class)?->getId());
    }

    #[DataProvider('stockProvider')]
    public function testServesThePartFromStockBeforeTheRemainder(int $stock, DeliveryDate $expectedPartDate, DeliveryDate $expectedRemainderDate): void
    {
        $context = $this->createContext(CartPrice::TAX_STATE_GROSS);
        $lineItem = $this->createLineItem(quantity: 3, unitPrice: 10.0, taxRules: new TaxRuleCollection([new TaxRule(19)]), context: $context)
            ->setDeliveryInformation(new DeliveryInformation(stock: $stock, weight: 0.0, freeDelivery: false, restockTime: 3, deliveryTime: self::createDeliveryTime(1, 2)));

        [$part, $remainder] = $this->splitter->split($this->createPosition($lineItem), 2, $context);

        static::assertEquals($expectedPartDate, $part->getDeliveryDate());
        static::assertEquals($expectedRemainderDate, $remainder->getDeliveryDate());
    }

    public static function stockProvider(): \Generator
    {
        $deliveryDate = new DeliveryDate(new \DateTimeImmutable('2026-11-06'), new \DateTimeImmutable('2026-11-07'));
        $restockDate = new DeliveryDate(new \DateTimeImmutable('2026-11-09'), new \DateTimeImmutable('2026-11-10'));

        yield 'stock covering the whole position delivers both parts regularly' => [3, $deliveryDate, $deliveryDate];
        yield 'stock covering only the part delivers the remainder after restock' => [2, $deliveryDate, $restockDate];
        yield 'stock short of the part delivers both parts after restock' => [1, $restockDate, $restockDate];
    }

    public function testKeepsAFutureReleaseDateForBothParts(): void
    {
        $context = $this->createContext(CartPrice::TAX_STATE_GROSS);
        $lineItem = $this->createLineItem(quantity: 3, unitPrice: 10.0, taxRules: new TaxRuleCollection([new TaxRule(19)]), context: $context)
            ->setDeliveryInformation(new DeliveryInformation(stock: 2, weight: 0.0, freeDelivery: false, restockTime: 3, deliveryTime: self::createDeliveryTime(1, 2)))
            ->setPayloadValue('releaseDate', (new \DateTimeImmutable('2026-12-01 10:00:00'))->format(Defaults::STORAGE_DATE_TIME_FORMAT));

        [$part, $remainder] = $this->splitter->split($this->createPosition($lineItem), 2, $context);

        $releaseDeliveryDate = new DeliveryDate(new \DateTimeImmutable('2026-12-02'), new \DateTimeImmutable('2026-12-03'));
        static::assertEquals($releaseDeliveryDate, $part->getDeliveryDate());
        static::assertEquals($releaseDeliveryDate, $remainder->getDeliveryDate());
    }

    public function testUsesTheShippingMethodDeliveryTimeIfTheItemHasNone(): void
    {
        $shippingMethodDeliveryTime = (new DeliveryTimeEntity())->assign([
            'unit' => DeliveryTimeEntity::DELIVERY_TIME_DAY,
            'min' => 6,
            'max' => 7,
            'translated' => ['name' => '6-7 days'],
        ]);
        $context = $this->createContext(CartPrice::TAX_STATE_GROSS, $shippingMethodDeliveryTime);
        $lineItem = $this->createLineItem(quantity: 3, unitPrice: 10.0, taxRules: new TaxRuleCollection([new TaxRule(19)]), context: $context)
            ->setDeliveryInformation(new DeliveryInformation(stock: 3, weight: 0.0, freeDelivery: false));

        [$part] = $this->splitter->split($this->createPosition($lineItem), 2, $context);

        static::assertEquals(
            new DeliveryDate(new \DateTimeImmutable('2026-11-11'), new \DateTimeImmutable('2026-11-12')),
            $part->getDeliveryDate(),
        );
    }

    public function testKeepsThePositionDeliveryDateIfTheItemHasNoDeliveryInformation(): void
    {
        $context = $this->createContext(CartPrice::TAX_STATE_GROSS);
        $positionDate = new DeliveryDate(new \DateTimeImmutable('2026-11-20'), new \DateTimeImmutable('2026-11-21'));
        $position = $this->createPosition(
            $this->createLineItem(quantity: 3, unitPrice: 10.0, taxRules: new TaxRuleCollection([new TaxRule(19)]), context: $context),
            $positionDate,
        );

        [$part, $remainder] = $this->splitter->split($position, 2, $context);

        static::assertEquals($positionDate, $part->getDeliveryDate());
        static::assertEquals($positionDate, $remainder->getDeliveryDate());
    }

    #[DataProvider('invalidQuantityProvider')]
    public function testRefusesAQuantityThatLeavesNoPartOrNoRemainder(int $quantity): void
    {
        $context = $this->createContext(CartPrice::TAX_STATE_GROSS);
        $position = $this->createPosition($this->createLineItem(quantity: 3, unitPrice: 10.0, taxRules: new TaxRuleCollection([new TaxRule(19)]), context: $context));

        $this->expectExceptionObject(CartException::invalidDeliveryPositionSplitQuantity('line-item-id', $quantity, 3));

        $this->splitter->split($position, $quantity, $context);
    }

    public static function invalidQuantityProvider(): \Generator
    {
        yield 'zero leaves an empty part' => [0];
        yield 'the whole position leaves an empty remainder' => [3];
        yield 'more than the position holds' => [4];
    }

    public function testRefusesAPositionWhoseLineItemHasChildren(): void
    {
        $context = $this->createContext(CartPrice::TAX_STATE_GROSS);
        $lineItem = $this->createLineItem(quantity: 3, unitPrice: 10.0, taxRules: new TaxRuleCollection([new TaxRule(19)]), context: $context)
            ->setDeliveryInformation(new DeliveryInformation(stock: 3, weight: 0.0, freeDelivery: false, deliveryTime: self::createDeliveryTime(1, 2)))
            ->setChildren(new LineItemCollection([new LineItem('child-id', LineItem::PRODUCT_LINE_ITEM_TYPE, null, 3)]));

        $this->expectExceptionObject(CartException::deliveryPositionNotSplittable('line-item-id'));

        $this->splitter->split($this->createPosition($lineItem), 2, $context);
    }

    private function createContext(string $taxState, ?DeliveryTimeEntity $shippingMethodDeliveryTime = null): SalesChannelContext
    {
        $shippingMethod = new ShippingMethodEntity();
        if ($shippingMethodDeliveryTime !== null) {
            $shippingMethod->setDeliveryTime($shippingMethodDeliveryTime);
        }

        $context = static::createStub(SalesChannelContext::class);
        $context->method('getTaxState')->willReturn($taxState);
        $context->method('getItemRounding')->willReturn(new CashRoundingConfig(2, 0.01, true));
        $context->method('getShippingMethod')->willReturn($shippingMethod);

        return $context;
    }

    private function createLineItem(int $quantity, float $unitPrice, TaxRuleCollection $taxRules, SalesChannelContext $context): LineItem
    {
        return (new LineItem('line-item-id', LineItem::PRODUCT_LINE_ITEM_TYPE, null, $quantity))
            ->setStackable(true)
            ->setPrice($this->quantityPriceCalculator->calculate(new QuantityPriceDefinition($unitPrice, $taxRules, $quantity), $context));
    }

    private function createPosition(LineItem $lineItem, ?DeliveryDate $deliveryDate = null): DeliveryPosition
    {
        $price = $lineItem->getPrice();
        static::assertNotNull($price);

        return new DeliveryPosition(
            $lineItem->getId(),
            $lineItem,
            $lineItem->getQuantity(),
            $price,
            $deliveryDate ?? new DeliveryDate(new \DateTimeImmutable('2026-11-06'), new \DateTimeImmutable('2026-11-07')),
        );
    }

    private static function createDeliveryTime(int $min, int $max): DeliveryTime
    {
        return (new DeliveryTime())->assign([
            'name' => $min . '-' . $max . ' days',
            'min' => $min,
            'max' => $max,
            'unit' => DeliveryTimeEntity::DELIVERY_TIME_DAY,
        ]);
    }

    /**
     * @return list<array{float, float}>
     */
    private static function taxes(DeliveryPosition $position): array
    {
        $taxes = [];
        foreach ($position->getPrice()->getCalculatedTaxes() as $tax) {
            $taxes[] = [$tax->getTaxRate(), $tax->getTax()];
        }

        return $taxes;
    }
}
