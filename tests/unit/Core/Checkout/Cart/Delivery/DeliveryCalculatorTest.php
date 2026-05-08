<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Delivery;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\Delivery\DeliveryCalculator;
use Shopware\Core\Checkout\Cart\Delivery\Struct\Delivery;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryCollection;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryDate;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryInformation;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryPosition;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryPositionCollection;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryTime;
use Shopware\Core\Checkout\Cart\LineItem\CartDataCollection;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Price\CashRounding;
use Shopware\Core\Checkout\Cart\Price\QuantityPriceCalculator;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Checkout\Cart\Tax\PercentageTaxRuleBuilder;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTax;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\CheckoutPermissions;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\DeliveryTime\DeliveryTimeEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 */
#[CoversClass(DeliveryCalculator::class)]
#[Package('checkout')]
class DeliveryCalculatorTest extends TestCase
{
    private DeliveryTime $deliveryTime;

    protected function setUp(): void
    {
        $this->deliveryTime = (new DeliveryTime())->assign([
            'min' => 1,
            'max' => 3,
            'unit' => 'day',
            'name' => '1-3 days',
        ]);
        $deliveryTimeEntity = new DeliveryTimeEntity();
        $deliveryTimeEntity->assign([
            'min' => 1,
            'max' => 3,
            'unit' => 'day',
            'name' => '1-3 days',
        ]);
    }

    public function testCalculateAdminShippingCostZero(): void
    {
        $context = $this->createMock(SalesChannelContext::class);
        $context
            ->method('getItemRounding')
            ->willReturn(new CashRoundingConfig(2, 0.01, true));

        $delivery = $this->getMockBuilder(Delivery::class)
            ->disableOriginalConstructor()
            ->getMock();
        $costs = new CalculatedPrice(0.0, 0.0, new CalculatedTaxCollection(), new TaxRuleCollection());
        $delivery
            ->expects($this->atLeastOnce())
            ->method('getShippingCosts')->willReturn($costs);
        $newCosts = null;
        $delivery
            ->expects($this->once())
            ->method('setShippingCosts')
            ->willReturnCallback(static function ($costsParameter) use (&$newCosts): void {
                $newCosts = $costsParameter;
            });

        $lineItem = new LineItem(Uuid::randomHex(), 'product');
        $lineItem->setDeliveryInformation(
            new DeliveryInformation(
                10,
                12.0,
                false,
                null,
                $this->deliveryTime
            )
        );
        $lineItem->setPrice(new CalculatedPrice(1, 1, new CalculatedTaxCollection(), new TaxRuleCollection()));
        $price = $lineItem->getPrice();
        static::assertNotNull($price);

        $delivery
            ->expects($this->once())
            ->method('getPositions')
            ->willReturn(
                new DeliveryPositionCollection(
                    [
                        new DeliveryPosition(
                            Uuid::randomHex(),
                            $lineItem,
                            1,
                            $price,
                            new DeliveryDate(new \DateTime(), new \DateTime())
                        ),
                    ]
                )
            );

        $data = new CartDataCollection();

        $cart = new Cart('test');
        $cartBehavior = new CartBehavior([
            CheckoutPermissions::SKIP_DELIVERY_PRICE_RECALCULATION => true,
        ]);
        $cart->setBehavior($cartBehavior);

        $quantityPriceCalculatorMock = $this->createMock(QuantityPriceCalculator::class);
        $quantityPriceCalculatorMock
            ->expects($this->once())
            ->method('calculate')
            ->willReturn($costs);

        $deliveryCalculator = new DeliveryCalculator(
            $quantityPriceCalculatorMock,
            $this->createMock(PercentageTaxRuleBuilder::class),
            $this->createMock(CashRounding::class),
        );

        $deliveryCalculator->calculate($data, $cart, new DeliveryCollection([$delivery]), $context);

        static::assertInstanceOf(CalculatedPrice::class, $newCosts);
        static::assertSame($costs->getUnitPrice(), $newCosts->getUnitPrice());
        static::assertSame($costs->getTotalPrice(), $newCosts->getTotalPrice());
    }

    public function testCalculateShippingFreeShippingCost(): void
    {
        $context = $this->createMock(SalesChannelContext::class);
        $context
            ->method('getItemRounding')
            ->willReturn(new CashRoundingConfig(2, 0.01, true));

        $delivery = $this->getMockBuilder(Delivery::class)
            ->disableOriginalConstructor()
            ->getMock();
        $costs = new CalculatedPrice(0.0, 0.0, new CalculatedTaxCollection(), new TaxRuleCollection());
        $delivery
            ->expects($this->atLeastOnce())
            ->method('getShippingCosts')
            ->willReturn($costs);
        $newCosts = null;
        $delivery
            ->expects($this->once())
            ->method('setShippingCosts')
            ->willReturnCallback(static function ($costsParameter) use (&$newCosts): void {
                $newCosts = $costsParameter;
            });

        $deliveryInformation = new DeliveryInformation(
            10,
            12.0,
            true,
            null,
            $this->deliveryTime
        );
        $lineItem = new LineItem(Uuid::randomHex(), 'product');
        $lineItem->setDeliveryInformation(
            $deliveryInformation
        );
        $lineItem->setPrice(new CalculatedPrice(1, 1, new CalculatedTaxCollection(), new TaxRuleCollection()));
        $price = $lineItem->getPrice();

        static::assertNotNull($price);

        $delivery
            ->expects($this->atLeastOnce())
            ->method('getPositions')
            ->willReturn(
                new DeliveryPositionCollection(
                    [
                        new DeliveryPosition(
                            Uuid::randomHex(),
                            $lineItem,
                            1,
                            $price,
                            new DeliveryDate(new \DateTime(), new \DateTime())
                        ),
                    ]
                )
            );

        $quantityPriceCalculatorMock = $this->createMock(QuantityPriceCalculator::class);
        $quantityPriceCalculatorMock
            ->expects($this->once())
            ->method('calculate')
            ->willReturn($costs);

        $deliveryCalculator = new DeliveryCalculator(
            $quantityPriceCalculatorMock,
            $this->createMock(PercentageTaxRuleBuilder::class),
            $this->createMock(CashRounding::class),
        );

        $deliveryCalculator->calculate(new CartDataCollection(), new Cart('test'), new DeliveryCollection([$delivery]), $context);

        static::assertInstanceOf(CalculatedPrice::class, $newCosts);
        static::assertSame($costs->getUnitPrice(), $newCosts->getUnitPrice());
        static::assertSame($costs->getTotalPrice(), $newCosts->getTotalPrice());
    }

    public function testCalculateManualShippingCost(): void
    {
        $context = $this->createMock(SalesChannelContext::class);
        $context
            ->expects($this->atLeastOnce())
            ->method('buildTaxRules')
            ->willReturn(new TaxRuleCollection());

        $delivery = $this->getMockBuilder(Delivery::class)
            ->disableOriginalConstructor()
            ->getMock();

        $costs = new CalculatedPrice(10.00, 10.0, new CalculatedTaxCollection(), new TaxRuleCollection());

        $shippingMethod = new ShippingMethodEntity();
        $shippingMethod->setTaxType('fixed');
        $shippingMethod->setTaxId(Uuid::randomHex());

        $delivery
            ->expects($this->atLeastOnce())
            ->method('getShippingCosts')
            ->willReturn($costs);
        $delivery
            ->expects($this->atLeastOnce())
            ->method('getShippingMethod')
            ->willReturn($shippingMethod);

        $newCosts = null;
        $delivery
            ->expects($this->once())
            ->method('setShippingCosts')
            ->willReturnCallback(static function ($costsParameter) use (&$newCosts): void {
                $newCosts = $costsParameter;
            });

        $quantityPriceCalculatorMock = $this->createMock(QuantityPriceCalculator::class);
        $quantityPriceCalculatorMock
            ->expects($this->once())
            ->method('calculate')
            ->willReturn($costs);

        $deliveryCalculator = new DeliveryCalculator(
            $quantityPriceCalculatorMock,
            $this->createMock(PercentageTaxRuleBuilder::class),
            $this->createMock(CashRounding::class),
        );

        $deliveryCalculator->calculate(new CartDataCollection(), new Cart('test'), new DeliveryCollection([$delivery]), $context);

        static::assertInstanceOf(CalculatedPrice::class, $newCosts);
        static::assertSame($costs->getUnitPrice(), $newCosts->getUnitPrice());
        static::assertSame($costs->getTotalPrice(), $newCosts->getTotalPrice());
    }

    public function testShippingCostTaxPercentagesUseRoundedLineItemTotal(): void
    {
        $context = $this->createMock(SalesChannelContext::class);
        $totalRoundingConfig = new CashRoundingConfig(2, 0.01, true);

        $context->method('getTotalRounding')->willReturn($totalRoundingConfig);

        $shippingMethod = new ShippingMethodEntity();
        $shippingMethod->setTaxType(ShippingMethodEntity::TAX_TYPE_AUTO);

        $delivery = $this->getMockBuilder(Delivery::class)
            ->disableOriginalConstructor()
            ->getMock();

        $delivery->method('getShippingCosts')->willReturn(
            new CalculatedPrice(5.00, 5.00, new CalculatedTaxCollection(), new TaxRuleCollection())
        );
        $delivery->method('getShippingMethod')->willReturn($shippingMethod);

        $lineItem1 = new LineItem(Uuid::randomHex(), 'product');
        $lineItem1->setDeliveryInformation(new DeliveryInformation(1, 1.0, false, null, $this->deliveryTime));
        $lineItem1->setPrice(new CalculatedPrice(
            0.10,
            0.10,
            new CalculatedTaxCollection([new CalculatedTax(0.016, 19, 0.10)]),
            new TaxRuleCollection()
        ));

        $lineItem2 = new LineItem(Uuid::randomHex(), 'product');
        $lineItem2->setDeliveryInformation(new DeliveryInformation(1, 1.0, false, null, $this->deliveryTime));
        $lineItem2->setPrice(new CalculatedPrice(
            0.20,
            0.20,
            new CalculatedTaxCollection([new CalculatedTax(0.013, 7, 0.20)]),
            new TaxRuleCollection()
        ));

        $price1 = $lineItem1->getPrice();
        static::assertNotNull($price1);
        $price2 = $lineItem2->getPrice();
        static::assertNotNull($price2);

        $delivery->method('getPositions')->willReturn(new DeliveryPositionCollection([
            new DeliveryPosition(
                Uuid::randomHex(),
                $lineItem1,
                1,
                $price1,
                new DeliveryDate(new \DateTime(), new \DateTime())
            ),

            new DeliveryPosition(
                Uuid::randomHex(),
                $lineItem2,
                1,
                $price2,
                new DeliveryDate(new \DateTime(), new \DateTime())
            ),
        ]));

        $capturedDefinition = null;
        $quantityPriceCalculatorMock = $this->createMock(QuantityPriceCalculator::class);

        $quantityPriceCalculatorMock
            ->expects($this->once())
            ->method('calculate')
            ->willReturnCallback(
                static function (QuantityPriceDefinition $definition) use (&$capturedDefinition): CalculatedPrice {
                    $capturedDefinition = $definition;

                    return new CalculatedPrice(5.00, 5.00, new CalculatedTaxCollection(), new TaxRuleCollection());
                }
            );

        $cashRoundingMock = $this->createMock(CashRounding::class);
        $cashRoundingMock
            ->expects($this->once())
            ->method('mathRound')
            ->with(
                static::callback(static fn (float $price): bool => abs($price - 0.30) < 0.01),
                $totalRoundingConfig
            )
            ->willReturn(0.30);

        $deliveryCalculator = new DeliveryCalculator(
            $quantityPriceCalculatorMock,
            new PercentageTaxRuleBuilder(),
            $cashRoundingMock
        );

        $deliveryCalculator->calculate(
            new CartDataCollection(),
            new Cart('test'),
            new DeliveryCollection([$delivery]),
            $context
        );

        static::assertInstanceOf(QuantityPriceDefinition::class, $capturedDefinition);

        $taxRules = $capturedDefinition->getTaxRules();

        static::assertCount(2, $taxRules);

        $totalPercentage = 0.0;

        foreach ($taxRules as $taxRule) {
            $totalPercentage += $taxRule->getPercentage();
        }

        static::assertEqualsWithDelta(100.0, $totalPercentage, 0.001);
    }
}
