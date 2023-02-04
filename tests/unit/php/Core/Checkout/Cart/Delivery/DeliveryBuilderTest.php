<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Delivery;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\Delivery\DeliveryBuilder;
use Shopware\Core\Checkout\Cart\Delivery\Struct\Delivery;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryDate;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryInformation;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryTime;
use Shopware\Core\Checkout\Cart\Delivery\Struct\ShippingLocation;
use Shopware\Core\Checkout\Cart\LineItem\CartDataCollection;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Shipping\Exception\ShippingMethodNotFoundException;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\DeliveryTime\DeliveryTimeEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 *
 * @covers \Shopware\Core\Checkout\Cart\Delivery\DeliveryBuilder
 */
class DeliveryBuilderTest extends TestCase
{
    public function testBuildThrowsIfNoShippingMethodCanBeFound(): void
    {
        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannelContext->expects(static::any())
            ->method('getShippingMethod')
            ->willReturn(
                (new ShippingMethodEntity())->assign([
                    'id' => 'shipping-method-id',
                ])
            );

        $this->expectException(ShippingMethodNotFoundException::class);
        (new DeliveryBuilder())->build(
            new Cart('cart-token'),
            new CartDataCollection([]),
            $salesChannelContext,
            new CartBehavior(),
        );
    }

    public function testBuildDelegatesToBuildByUsingShippingMethod(): void
    {
        $shippingMethod = (new ShippingMethodEntity())->assign([
            'id' => 'shipping-method-id',
        ]);

        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannelContext->expects(static::any())
            ->method('getShippingMethod')
            ->willReturn($shippingMethod);

        $cart = new Cart('cart-token');
        $cartDataCollection = new CartDataCollection([
            'shipping-method-shipping-method-id' => $shippingMethod,
        ]);

        /** @var DeliveryBuilder&MockObject $deliveryBuilder */
        $deliveryBuilder = $this->getMockBuilder(DeliveryBuilder::class)
            // don't mock build because it is the function under test
            ->onlyMethods(['buildByUsingShippingMethod'])
            ->getMock();

        $deliveryBuilder->expects(static::once())
            ->method('buildByUsingShippingMethod')
            ->with($cart, $shippingMethod, $salesChannelContext);

        $deliveryBuilder->build(
            $cart,
            $cartDataCollection,
            $salesChannelContext,
            new CartBehavior(),
        );
    }

    /**
     * @dataProvider getLineItemsThatResultInAnEmptyDelivery
     */
    public function testLineItemResultInAnEmptyDelivery(LineItemCollection $lineItems): void
    {
        $cart = new Cart('cart-token');
        $cart->setLineItems($lineItems);

        $deliveries = (new DeliveryBuilder())->buildByUsingShippingMethod(
            $cart,
            new ShippingMethodEntity(),
            $this->createMock(SalesChannelContext::class),
        );

        static::assertEquals(0, $deliveries->count());
    }

    /**
     * @return iterable<array{0: LineItemCollection}>
     */
    public function getLineItemsThatResultInAnEmptyDelivery(): iterable
    {
        yield 'DeliveryCollection is empty if LineItemCollection is empty' => [new LineItemCollection()];

        yield 'DeliveryCollection is empty if no LineItem has set deliveryInformation' => [new LineItemCollection([
            (new LineItem('line-item-id', LineItem::CUSTOM_LINE_ITEM_TYPE, null, 1))
                ->assign(['deliveryInformation' => null]),
        ])];

        yield 'DeliveryCollection is empty if LineItems deliveryTime is null' => [new LineItemCollection([
            (new LineItem('line-item-id', LineItem::CUSTOM_LINE_ITEM_TYPE, null, 1))
                ->assign(['deliveryInformation' => new DeliveryInformation(10, 1, false, null, null)]),
        ])];

        $deliveryTime = $this->createDeliveryTime(1, 3);

        yield 'DeliveryCollection is empty if LineItems price is not set' => [new LineItemCollection([
            (new LineItem('line-item-id', LineItem::CUSTOM_LINE_ITEM_TYPE, null, 1))
                ->assign([
                    'deliveryInformation' => new DeliveryInformation(10, 1, false, 5, $deliveryTime),
                    'price' => null,
                ]),
        ])];
    }

    /**
     * @dataProvider provideLineItemDataForSingleDelivery
     */
    public function testDeliveryTimesForSingleDelivery(LineItemCollection $lineItems, DeliveryDate $expectedDeliveryDate): void
    {
        $cart = new Cart('cart-token');
        $cart->setLineItems($lineItems);

        $shippingMethod = new ShippingMethodEntity();
        $shippingMethod->setDeliveryTime($this->createDeliveryTimeEntity(DeliveryTimeEntity::DELIVERY_TIME_DAY, 2, 3));

        $deliveryLocation = new ShippingLocation(new CountryEntity(), null, null);

        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannelContext->expects(static::once())
            ->method('getShippingLocation')
            ->willReturn($deliveryLocation);

        $deliveryCollection = (new DeliveryBuilder())->buildByUsingShippingMethod($cart, $shippingMethod, $salesChannelContext);

        static::assertEquals(1, $deliveryCollection->count());

        /** @var Delivery $delivery */
        $delivery = $deliveryCollection->first();

        static::assertSame($shippingMethod, $delivery->getShippingMethod());
        static::assertSame($deliveryLocation, $delivery->getLocation());
        static::assertEquals(
            new CalculatedPrice(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection()),
            $delivery->getShippingCosts(),
        );

        static::assertEquals($expectedDeliveryDate, $delivery->getDeliveryDate());
    }

    /**
     * @return iterable<array{0: LineItemCollection, 1: DeliveryDate}>
     */
    public function provideLineItemDataForSingleDelivery(): iterable
    {
        yield 'Shipping method delivery data is used if position has no own delivery time' => [
            new LineItemCollection([
                (new LineItem('line-item-id', LineItem::CUSTOM_LINE_ITEM_TYPE, null, 1))
                    ->assign([
                        'deliveryInformation' => $this->createDeliveryInformation(null, 0),
                        'price' => new CalculatedPrice(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection()),
                    ]),
            ]),
            DeliveryDate::createFromDeliveryTime($this->createDeliveryTime(2, 3)),
        ];

        yield 'It takes delivery time of position if line item is in stock' => [
            new LineItemCollection([
                (new LineItem('line-item-id', LineItem::CUSTOM_LINE_ITEM_TYPE, null, 1))
                    ->assign([
                        'deliveryInformation' => $this->createDeliveryInformation($this->createDeliveryTime(4, 5), 0),
                        'price' => new CalculatedPrice(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection()),
                    ]),
            ]),
            DeliveryDate::createFromDeliveryTime($this->createDeliveryTime(4, 5)),
        ];

        yield 'It adds restock time to Delivery Time if item is out of stock' => [
            new LineItemCollection([
                (new LineItem('line-item-id', LineItem::CUSTOM_LINE_ITEM_TYPE, null, 20))
                    ->assign([
                        'deliveryInformation' => $this->createDeliveryInformation($this->createDeliveryTime(4, 5), 2),
                        'price' => new CalculatedPrice(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection()),
                    ]),
            ]),
            DeliveryDate::createFromDeliveryTime($this->createDeliveryTime(6, 7)),
        ];

        yield 'It takes delivery time of nested line item if parent has none' => [
            new LineItemCollection([
                (new LineItem('parent-line-item', LineItem::CUSTOM_LINE_ITEM_TYPE, null, 1))
                    ->assign([
                        'children' => new LineItemCollection([
                            (new LineItem('line-item-id', LineItem::CUSTOM_LINE_ITEM_TYPE, null, 1))
                                ->assign([
                                    'deliveryInformation' => $this->createDeliveryInformation($this->createDeliveryTime(4, 5), 0),
                                    'price' => new CalculatedPrice(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection()),
                                ]),
                        ]),
                    ]),
            ]),
            DeliveryDate::createFromDeliveryTime($this->createDeliveryTime(4, 5)),
        ];

        yield 'It calculates the earliest and latest delivery time from all positions' => [
            new LineItemCollection([
                (new LineItem('first-line-item-id', LineItem::CUSTOM_LINE_ITEM_TYPE, null, 1))
                    ->assign([
                        'deliveryInformation' => $this->createDeliveryInformation($this->createDeliveryTime(2, 8), 2),
                        'price' => new CalculatedPrice(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection()),
                    ]),
                (new LineItem('second-line-item-id', LineItem::CUSTOM_LINE_ITEM_TYPE, null, 1))
                    ->assign([
                        'deliveryInformation' => $this->createDeliveryInformation($this->createDeliveryTime(4, 6), 2),
                        'price' => new CalculatedPrice(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection()),
                    ]),
            ]),
            DeliveryDate::createFromDeliveryTime($this->createDeliveryTime(4, 8)),
        ];

        yield 'It adds one day buffer if earliest and latest is the same' => [
            new LineItemCollection([
                (new LineItem('line-item-id', LineItem::CUSTOM_LINE_ITEM_TYPE, null, 1))
                    ->assign([
                        'deliveryInformation' => $this->createDeliveryInformation($this->createDeliveryTime(2, 2), 2),
                        'price' => new CalculatedPrice(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection()),
                    ]),
            ]),
            DeliveryDate::createFromDeliveryTime($this->createDeliveryTime(2, 3)),
        ];
    }

    private function createDeliveryTimeEntity(string $unit, int $min, int $max): DeliveryTimeEntity
    {
        return (new DeliveryTimeEntity())->assign([
            'unit' => $unit,
            'min' => $min,
            'max' => $max,
            'translated' => [
                'name' => 'deliveryTime',
            ],
        ]);
    }

    private function createDeliveryTime(int $min, int $max): DeliveryTime
    {
        return DeliveryTime::createFromEntity($this->createDeliveryTimeEntity(DeliveryTimeEntity::DELIVERY_TIME_DAY, $min, $max));
    }

    private function createDeliveryInformation(?DeliveryTime $deliveryTime, int $restockTime): DeliveryInformation
    {
        return new DeliveryInformation(
            10,
            0.0,
            false,
            $restockTime,
            $deliveryTime
        );
    }
}
