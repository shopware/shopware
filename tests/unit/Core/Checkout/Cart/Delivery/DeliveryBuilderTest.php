<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Delivery;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\CartException;
use Shopware\Core\Checkout\Cart\Delivery\DeliveryBuilder;
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
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\DeliveryTime\DeliveryTimeEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 */
#[CoversClass(DeliveryBuilder::class)]
#[Package('checkout')]
class DeliveryBuilderTest extends TestCase
{
    public function testBuildThrowsIfNoShippingMethodCanBeFound(): void
    {
        $salesChannelContext = static::createStub(SalesChannelContext::class);
        $salesChannelContext->method('getShippingMethod')
            ->willReturn(
                (new ShippingMethodEntity())->assign([
                    'id' => 'shipping-method-id',
                ])
            );

        $this->expectExceptionObject(CartException::shippingMethodNotFound('shipping-method-id'));
        (new DeliveryBuilder())->build(
            new Cart('cart-token'),
            new CartDataCollection([]),
            $salesChannelContext,
            new CartBehavior(),
        );
    }

    public function testBuildDelegatesToBuildByUsingShippingMethod(): void
    {
        // build() derives the lookup key from the context's shipping method id but resolves the
        // actual method from the cart data collection, then delegates to buildByUsingShippingMethod().
        // Use a distinct object in the data collection and assert it is the one that ends up on the
        // produced delivery, rather than partial-mocking the method under test.
        $contextShippingMethod = (new ShippingMethodEntity())->assign(['id' => 'shipping-method-id']);

        $resolvedShippingMethod = (new ShippingMethodEntity())->assign(['id' => 'shipping-method-id']);
        $resolvedShippingMethod->setDeliveryTime(self::createDeliveryTimeEntity(DeliveryTimeEntity::DELIVERY_TIME_DAY, 2, 3));

        $salesChannelContext = static::createStub(SalesChannelContext::class);
        $salesChannelContext->method('getShippingMethod')->willReturn($contextShippingMethod);
        $salesChannelContext->method('getShippingLocation')->willReturn(new ShippingLocation(new CountryEntity(), null, null));

        $cart = new Cart('cart-token');
        $cart->setLineItems(new LineItemCollection([
            (new LineItem('line-item-id', LineItem::CUSTOM_LINE_ITEM_TYPE, null, 1))
                ->assign([
                    'deliveryInformation' => self::createDeliveryInformation(null, 0),
                    'price' => new CalculatedPrice(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection()),
                    'shippingCostAware' => true,
                ]),
        ]));

        $cartDataCollection = new CartDataCollection([
            'shipping-method-shipping-method-id' => $resolvedShippingMethod,
        ]);

        $deliveries = (new DeliveryBuilder())->build(
            $cart,
            $cartDataCollection,
            $salesChannelContext,
            new CartBehavior(),
        );

        static::assertCount(1, $deliveries);
        $delivery = $deliveries->first();
        static::assertNotNull($delivery);
        static::assertSame($resolvedShippingMethod, $delivery->getShippingMethod());
    }

    #[DataProvider('getLineItemsThatResultInAnEmptyDelivery')]
    public function testLineItemResultInAnEmptyDelivery(LineItemCollection $lineItems): void
    {
        $cart = new Cart('cart-token');
        $cart->setLineItems($lineItems);

        $deliveries = (new DeliveryBuilder())->buildByUsingShippingMethod(
            $cart,
            new ShippingMethodEntity(),
            static::createStub(SalesChannelContext::class),
        );

        static::assertCount(0, $deliveries);
    }

    /**
     * @return iterable<array{0: LineItemCollection}>
     */
    public static function getLineItemsThatResultInAnEmptyDelivery(): iterable
    {
        yield 'DeliveryCollection is empty if LineItemCollection is empty' => [new LineItemCollection()];

        yield 'DeliveryCollection is empty if LineItem is not aware of shipping costs' => [new LineItemCollection([
            (new LineItem('line-item-id', LineItem::CUSTOM_LINE_ITEM_TYPE, null, 1))
                ->assign(['shippingCostAware' => false, 'deliveryInformation' => new DeliveryInformation(10, 1, false, 5, self::createDeliveryTime(1, 3))]),
        ])];

        yield 'DeliveryCollection is empty if no LineItem has set deliveryInformation' => [new LineItemCollection([
            (new LineItem('line-item-id', LineItem::CUSTOM_LINE_ITEM_TYPE, null, 1))
                ->assign(['deliveryInformation' => null, 'shippingCostAware' => true]),
        ])];

        yield 'DeliveryCollection is empty if LineItems deliveryTime is null' => [new LineItemCollection([
            (new LineItem('line-item-id', LineItem::CUSTOM_LINE_ITEM_TYPE, null, 1))
                ->assign(['deliveryInformation' => new DeliveryInformation(10, 1, false, null, null), 'shippingCostAware' => true]),
        ])];

        $deliveryTime = self::createDeliveryTime(1, 3);

        yield 'DeliveryCollection is empty if LineItems price is not set' => [new LineItemCollection([
            (new LineItem('line-item-id', LineItem::CUSTOM_LINE_ITEM_TYPE, null, 1))
                ->assign([
                    'deliveryInformation' => new DeliveryInformation(10, 1, false, 5, $deliveryTime),
                    'price' => null,
                    'shippingCostAware' => null,
                ]),
        ])];
    }

    #[DataProvider('provideLineItemDataForSingleDelivery')]
    public function testDeliveryTimesForSingleDelivery(LineItemCollection $lineItems, DeliveryDate $expectedDeliveryDate): void
    {
        $cart = new Cart('cart-token');
        $cart->setLineItems($lineItems);

        $shippingMethod = new ShippingMethodEntity();
        $shippingMethod->setDeliveryTime(self::createDeliveryTimeEntity(DeliveryTimeEntity::DELIVERY_TIME_DAY, 2, 3));

        $deliveryLocation = new ShippingLocation(new CountryEntity(), null, null);

        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannelContext->expects($this->once())
            ->method('getShippingLocation')
            ->willReturn($deliveryLocation);

        $deliveryCollection = (new DeliveryBuilder())->buildByUsingShippingMethod($cart, $shippingMethod, $salesChannelContext);

        static::assertCount(1, $deliveryCollection);

        $delivery = $deliveryCollection->first();
        static::assertNotNull($delivery);

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
    public static function provideLineItemDataForSingleDelivery(): iterable
    {
        yield 'Shipping method delivery data is used if position has no own delivery time' => [
            new LineItemCollection([
                (new LineItem('line-item-id', LineItem::CUSTOM_LINE_ITEM_TYPE, null, 1))
                    ->assign([
                        'deliveryInformation' => self::createDeliveryInformation(null, 0),
                        'price' => new CalculatedPrice(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection()),
                        'shippingCostAware' => true,
                    ]),
            ]),
            DeliveryDate::createFromDeliveryTime(self::createDeliveryTime(2, 3)),
        ];

        yield 'Shipping method delivery data is used if LineItem has no delivery information' => [
            new LineItemCollection([
                (new LineItem('line-item-id', LineItem::PROMOTION_LINE_ITEM_TYPE, null, 1))
                    ->assign([
                        'deliveryInformation' => null,
                        'price' => new CalculatedPrice(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection()),
                        'shippingCostAware' => true,
                    ]),
            ]),
            DeliveryDate::createFromDeliveryTime(self::createDeliveryTime(2, 3)),
        ];

        yield 'It takes delivery time of position if line item is in stock' => [
            new LineItemCollection([
                (new LineItem('line-item-id', LineItem::CUSTOM_LINE_ITEM_TYPE, null, 1))
                    ->assign([
                        'deliveryInformation' => self::createDeliveryInformation(self::createDeliveryTime(4, 5), 0),
                        'price' => new CalculatedPrice(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection()),
                        'shippingCostAware' => true,
                    ]),
            ]),
            DeliveryDate::createFromDeliveryTime(self::createDeliveryTime(4, 5)),
        ];

        $releaseDate = new \DateTimeImmutable('2030-01-15 10:00:00');

        yield 'It uses future release date as availability start if line item is in stock' => [
            new LineItemCollection([
                (new LineItem('line-item-id', LineItem::CUSTOM_LINE_ITEM_TYPE, null, 1))
                    ->assign([
                        'deliveryInformation' => self::createDeliveryInformation(self::createDeliveryTime(2, 3), 0),
                        'payload' => ['releaseDate' => $releaseDate->format(Defaults::STORAGE_DATE_TIME_FORMAT)],
                        'price' => new CalculatedPrice(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection()),
                        'shippingCostAware' => true,
                    ]),
            ]),
            DeliveryDate::createFromDeliveryTimeAt(self::createDeliveryTime(2, 3), $releaseDate),
        ];

        yield 'It ignores invalid release date if line item is in stock' => [
            new LineItemCollection([
                (new LineItem('line-item-id', LineItem::CUSTOM_LINE_ITEM_TYPE, null, 1))
                    ->assign([
                        'deliveryInformation' => self::createDeliveryInformation(self::createDeliveryTime(2, 3), 0),
                        'payload' => ['releaseDate' => '$invalid'],
                        'price' => new CalculatedPrice(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection()),
                        'shippingCostAware' => true,
                    ]),
            ]),
            DeliveryDate::createFromDeliveryTime(self::createDeliveryTime(2, 3)),
        ];

        yield 'It ignores past release date if line item is in stock' => [
            new LineItemCollection([
                (new LineItem('line-item-id', LineItem::CUSTOM_LINE_ITEM_TYPE, null, 1))
                    ->assign([
                        'deliveryInformation' => self::createDeliveryInformation(self::createDeliveryTime(2, 3), 0),
                        'payload' => ['releaseDate' => '2020-01-15 10:00:00.000'],
                        'price' => new CalculatedPrice(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection()),
                        'shippingCostAware' => true,
                    ]),
            ]),
            DeliveryDate::createFromDeliveryTime(self::createDeliveryTime(2, 3)),
        ];

        yield 'It adds restock time to Delivery Time if item is out of stock' => [
            new LineItemCollection([
                (new LineItem('line-item-id', LineItem::CUSTOM_LINE_ITEM_TYPE, null, 20))
                    ->assign([
                        'deliveryInformation' => self::createDeliveryInformation(self::createDeliveryTime(4, 5), 2),
                        'price' => new CalculatedPrice(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection()),
                        'shippingCostAware' => true,
                    ]),
            ]),
            DeliveryDate::createFromDeliveryTime(self::createDeliveryTime(6, 7)),
        ];

        yield 'It uses later restock availability if release date is before restock date' => [
            new LineItemCollection([
                (new LineItem('line-item-id', LineItem::CUSTOM_LINE_ITEM_TYPE, null, 20))
                    ->assign([
                        'deliveryInformation' => self::createDeliveryInformation(self::createDeliveryTime(2, 3), 10),
                        'payload' => ['releaseDate' => (new \DateTimeImmutable('+1 day'))->format(Defaults::STORAGE_DATE_TIME_FORMAT)],
                        'price' => new CalculatedPrice(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection()),
                        'shippingCostAware' => true,
                    ]),
            ]),
            DeliveryDate::createFromDeliveryTime(self::createDeliveryTime(12, 13)),
        ];

        yield 'It takes delivery time of nested line item if parent has none' => [
            new LineItemCollection([
                (new LineItem('parent-line-item', LineItem::CUSTOM_LINE_ITEM_TYPE, null, 1))
                    ->assign([
                        'shippingCostAware' => true,
                        'children' => new LineItemCollection([
                            (new LineItem('line-item-id', LineItem::CUSTOM_LINE_ITEM_TYPE, null, 1))
                                ->assign([
                                    'deliveryInformation' => self::createDeliveryInformation(self::createDeliveryTime(4, 5), 0),
                                    'price' => new CalculatedPrice(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection()),
                                    'shippingCostAware' => true,
                                ]),
                        ]),
                    ]),
            ]),
            DeliveryDate::createFromDeliveryTime(self::createDeliveryTime(4, 5)),
        ];

        yield 'It calculates the earliest and latest delivery time from all positions' => [
            new LineItemCollection([
                (new LineItem('first-line-item-id', LineItem::CUSTOM_LINE_ITEM_TYPE, null, 1))
                    ->assign([
                        'deliveryInformation' => self::createDeliveryInformation(self::createDeliveryTime(2, 8), 2),
                        'price' => new CalculatedPrice(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection()),
                        'shippingCostAware' => true,
                    ]),
                (new LineItem('second-line-item-id', LineItem::CUSTOM_LINE_ITEM_TYPE, null, 1))
                    ->assign([
                        'deliveryInformation' => self::createDeliveryInformation(self::createDeliveryTime(4, 6), 2),
                        'price' => new CalculatedPrice(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection()),
                        'shippingCostAware' => true,
                    ]),
            ]),
            DeliveryDate::createFromDeliveryTime(self::createDeliveryTime(4, 8)),
        ];

        yield 'It adds one day buffer if earliest and latest is the same' => [
            new LineItemCollection([
                (new LineItem('line-item-id', LineItem::CUSTOM_LINE_ITEM_TYPE, null, 1))
                    ->assign([
                        'deliveryInformation' => self::createDeliveryInformation(self::createDeliveryTime(2, 2), 2),
                        'price' => new CalculatedPrice(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection()),
                        'shippingCostAware' => true,
                    ]),
            ]),
            DeliveryDate::createFromDeliveryTime(self::createDeliveryTime(2, 3)),
        ];
    }

    private static function createDeliveryTimeEntity(string $unit, int $min, int $max): DeliveryTimeEntity
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

    private static function createDeliveryTime(int $min, int $max): DeliveryTime
    {
        return DeliveryTime::createFromEntity(self::createDeliveryTimeEntity(DeliveryTimeEntity::DELIVERY_TIME_DAY, $min, $max));
    }

    private static function createDeliveryInformation(?DeliveryTime $deliveryTime, int $restockTime): DeliveryInformation
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
