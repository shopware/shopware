<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\TaxProvider;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartException;
use Shopware\Core\Checkout\Cart\Delivery\Struct\Delivery;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryCollection;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryDate;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryPosition;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryPositionCollection;
use Shopware\Core\Checkout\Cart\Delivery\Struct\ShippingLocation;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Price\AmountCalculator;
use Shopware\Core\Checkout\Cart\Price\CashRounding;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Tax\PercentageTaxRuleBuilder;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTax;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Cart\Tax\TaxCalculator;
use Shopware\Core\Checkout\Cart\TaxProvider\Struct\TaxProviderResult;
use Shopware\Core\Checkout\Cart\TaxProvider\TaxAdjustment;
use Shopware\Core\Checkout\Cart\TaxProvider\TaxAdjustmentCalculator;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;
use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\System\Country\Aggregate\CountryState\CountryStateEntity;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 *
 * @package checkout
 */
#[CoversClass(TaxAdjustment::class)]
class TaxAdjustmentTest extends TestCase
{
    private IdsCollection $ids;

    private TaxAdjustment $adjustment;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();

        $this->adjustment = new TaxAdjustment(
            new AmountCalculator(
                new CashRounding(),
                new PercentageTaxRuleBuilder(),
                new TaxAdjustmentCalculator()
            ),
            new CashRounding()
        );
    }

    public function testItThrowsOnEmptyLineItemPrice(): void
    {
        $struct = new TaxProviderResult(
            [
                $this->ids->get('line-item-1') => new CalculatedTaxCollection([
                    new CalculatedTax(
                        7,
                        7,
                        100
                    ),
                ]),
            ],
            [
                $this->ids->get('delivery-position-1') => new CalculatedTaxCollection([
                    new CalculatedTax(
                        7,
                        7,
                        100
                    ),
                ]),
            ],
        );

        $cart = $this->createCart();

        static::assertNotNull($cart->get($this->ids->get('line-item-1')));

        $cart->get($this->ids->get('line-item-1'))->setPrice(null);

        $context = $this->createMock(SalesChannelContext::class);
        $context
            ->method('getTotalRounding')
            ->willReturn(new CashRoundingConfig(2, 0.01, true));

        static::expectException(CartException::class);
        static::expectExceptionMessage('Line item with identifier ' . $this->ids->get('line-item-1') . ' has no price.');

        $this->adjustment->adjust($cart, $struct, $context);
    }

    public function testCalculateNet(): void
    {
        $result = new TaxProviderResult(
            [
                $this->ids->get('line-item-1') => new CalculatedTaxCollection([
                    new CalculatedTax(
                        7,
                        7,
                        100
                    ),
                ]),
            ],
            [
                $this->ids->get('delivery-position-1') => new CalculatedTaxCollection([
                    new CalculatedTax(
                        7,
                        7,
                        100
                    ),
                ]),
            ],
        );

        $cart = $this->createCart();

        $context = $this->createMock(SalesChannelContext::class);
        $context
            ->method('getTaxState')
            ->willReturn(CartPrice::TAX_STATE_NET);
        $context
            ->method('getTotalRounding')
            ->willReturn(new CashRoundingConfig(2, 0.01, true));

        $this->adjustment->adjust($cart, $result, $context);

        $lineItem = $cart->get($this->ids->get('line-item-1'));
        $delivery = $cart->getDeliveries()->first();

        static::assertNotNull($lineItem);
        static::assertNotNull($delivery);

        $lineItemPrice = $lineItem->getPrice();
        $deliveryPosition = $delivery->getPositions()->first();

        static::assertNotNull($lineItemPrice);
        static::assertNotNull($deliveryPosition);

        static::assertSame(214.0, $cart->getPrice()->getTotalPrice());
        static::assertSame(200.0, $cart->getPrice()->getNetPrice());
        static::assertSame(14.0, $cart->getPrice()->getCalculatedTaxes()->getAmount());
        static::assertSame(7.0, $lineItemPrice->getCalculatedTaxes()->getAmount());
        static::assertSame(7.0, $delivery->getShippingCosts()->getCalculatedTaxes()->getAmount());
        static::assertSame(7.0, $deliveryPosition->getPrice()->getCalculatedTaxes()->getAmount());
    }

    public function testCalculateGross(): void
    {
        $result = new TaxProviderResult(
            [
                $this->ids->get('line-item-1') => new CalculatedTaxCollection([
                    new CalculatedTax(
                        7,
                        7,
                        100
                    ),
                ]),
            ],
            [
                $this->ids->get('delivery-position-1') => new CalculatedTaxCollection([
                    new CalculatedTax(
                        7,
                        7,
                        100
                    ),
                ]),
            ],
        );

        $cart = $this->createCart();

        $context = $this->createMock(SalesChannelContext::class);
        $context
            ->method('getTaxState')
            ->willReturn(CartPrice::TAX_STATE_GROSS);
        $context
            ->method('getTotalRounding')
            ->willReturn(new CashRoundingConfig(2, 0.01, true));

        $this->adjustment->adjust($cart, $result, $context);

        $lineItem = $cart->get($this->ids->get('line-item-1'));
        $delivery = $cart->getDeliveries()->first();

        static::assertNotNull($lineItem);
        static::assertNotNull($delivery);

        $lineItemPrice = $lineItem->getPrice();
        $deliveryPosition = $delivery->getPositions()->first();

        static::assertNotNull($lineItemPrice);
        static::assertNotNull($deliveryPosition);

        static::assertSame(200.0, $cart->getPrice()->getTotalPrice());
        static::assertSame(186.0, $cart->getPrice()->getNetPrice());
        static::assertSame(14.0, $cart->getPrice()->getCalculatedTaxes()->getAmount());
        static::assertSame(7.0, $lineItemPrice->getCalculatedTaxes()->getAmount());
        static::assertSame(7.0, $delivery->getShippingCosts()->getCalculatedTaxes()->getAmount());
        static::assertSame(7.0, $deliveryPosition->getPrice()->getCalculatedTaxes()->getAmount());
    }

    public function testProvidedCartPriceOverridesCalculationNet(): void
    {
        $result = new TaxProviderResult(
            [
                $this->ids->get('line-item-1') => new CalculatedTaxCollection([
                    new CalculatedTax(
                        7,
                        7,
                        100
                    ),
                ]),
            ],
            [
                $this->ids->get('delivery-position-1') => new CalculatedTaxCollection([
                    new CalculatedTax(
                        7,
                        7,
                        100
                    ),
                ]),
            ],
            new CalculatedTaxCollection([
                new CalculatedTax(
                    20,
                    10,
                    200
                ),
            ])
        );

        $cart = $this->createCart();

        $context = $this->createMock(SalesChannelContext::class);
        $context
            ->method('getTaxState')
            ->willReturn(CartPrice::TAX_STATE_NET);
        $context
            ->method('getTotalRounding')
            ->willReturn(new CashRoundingConfig(2, 0.01, true));

        $this->adjustment->adjust($cart, $result, $context);

        $lineItem = $cart->get($this->ids->get('line-item-1'));
        $delivery = $cart->getDeliveries()->first();

        static::assertNotNull($lineItem);
        static::assertNotNull($delivery);

        $lineItemPrice = $lineItem->getPrice();
        $deliveryPosition = $delivery->getPositions()->first();

        static::assertNotNull($lineItemPrice);
        static::assertNotNull($deliveryPosition);

        // should not be overridden through tax provider
        static::assertSame(220.0, $cart->getPrice()->getTotalPrice());
        static::assertSame(200.0, $cart->getPrice()->getNetPrice());
        // should explicitly be overridden through tax provider - even if tax sums are wrong as in this case
        static::assertSame(20.0, $cart->getPrice()->getCalculatedTaxes()->getAmount());
        static::assertSame(7.0, $lineItemPrice->getCalculatedTaxes()->getAmount());
        static::assertSame(7.0, $delivery->getShippingCosts()->getCalculatedTaxes()->getAmount());
        static::assertSame(7.0, $deliveryPosition->getPrice()->getCalculatedTaxes()->getAmount());
    }

    public function testProvidedCartPriceOverridesCalculationGross(): void
    {
        $result = new TaxProviderResult(
            [
                $this->ids->get('line-item-1') => new CalculatedTaxCollection([
                    new CalculatedTax(
                        7,
                        7,
                        100
                    ),
                ]),
            ],
            [
                $this->ids->get('delivery-position-1') => new CalculatedTaxCollection([
                    new CalculatedTax(
                        7,
                        7,
                        100
                    ),
                ]),
            ],
            new CalculatedTaxCollection([
                new CalculatedTax(
                    20,
                    10,
                    200
                ),
            ])
        );

        $cart = $this->createCart();

        $context = $this->createMock(SalesChannelContext::class);
        $context
            ->method('getTaxState')
            ->willReturn(CartPrice::TAX_STATE_GROSS);
        $context
            ->method('getTotalRounding')
            ->willReturn(new CashRoundingConfig(2, 0.01, true));

        $this->adjustment->adjust($cart, $result, $context);

        $lineItem = $cart->get($this->ids->get('line-item-1'));
        $delivery = $cart->getDeliveries()->first();

        static::assertNotNull($lineItem);
        static::assertNotNull($delivery);

        $lineItemPrice = $lineItem->getPrice();
        $deliveryPosition = $delivery->getPositions()->first();

        static::assertNotNull($lineItemPrice);
        static::assertNotNull($deliveryPosition);

        // should not be overridden through tax provider
        static::assertSame(200.0, $cart->getPrice()->getTotalPrice());
        static::assertSame(180.0, $cart->getPrice()->getNetPrice());
        // should explicitly be overridden through tax provider - even if tax sums are wrong as in this case
        static::assertSame(20.0, $cart->getPrice()->getCalculatedTaxes()->getAmount());
        static::assertSame(7.0, $lineItemPrice->getCalculatedTaxes()->getAmount());
        static::assertSame(7.0, $delivery->getShippingCosts()->getCalculatedTaxes()->getAmount());
        static::assertSame(7.0, $deliveryPosition->getPrice()->getCalculatedTaxes()->getAmount());
    }

    public function testRemovesEmptyTaxes(): void
    {
        $cart = $this->createCart();
        $cart->setDeliveries(new DeliveryCollection());
        $cart->setPrice(
            new CartPrice(
                100,
                100,
                100,
                new CalculatedTaxCollection([
                    new CalculatedTax(0, 38, 100),
                ]),
                new TaxRuleCollection(),
                CartPrice::TAX_STATE_GROSS,
                100
            )
        );

        $result = new TaxProviderResult(
            [
                $this->ids->get('line-item-1') => new CalculatedTaxCollection([
                    new CalculatedTax(
                        7,
                        7,
                        100
                    ),
                    new CalculatedTax(
                        0,
                        19,
                        0
                    ),
                ]),
            ]
        );

        $context = $this->createMock(SalesChannelContext::class);
        $context
            ->method('getTaxState')
            ->willReturn(CartPrice::TAX_STATE_NET);
        $context
            ->method('getTotalRounding')
            ->willReturn(new CashRoundingConfig(2, 0.01, true));

        $this->adjustment->adjust($cart, $result, $context);

        $price = $cart->getPrice();
        $taxes = $price->getCalculatedTaxes();

        static::assertCount(1, $taxes);

        $tax = $taxes->first();
        static::assertNotNull($tax);

        static::assertSame(7.0, $tax->getTax());
        static::assertSame(7.0, $tax->getTaxRate());
        static::assertSame(100.0, $tax->getPrice());
    }

    public function testNestedLineItemCalculation(): void
    {
        $lineItemGrandChild1 = new LineItem(
            $this->ids->get('line-item-3'),
            LineItem::PRODUCT_LINE_ITEM_TYPE,
            $this->ids->get('line-item-3'),
            1,
        );

        $lineItemGrandChild1->assign(['uniqueIdentifier' => $this->ids->get('line-item-3')]);

        $lineItemGrandChild2 = new LineItem(
            $this->ids->get('line-item-4'),
            LineItem::PRODUCT_LINE_ITEM_TYPE,
            $this->ids->get('line-item-4'),
            1,
        );

        $lineItemGrandChild2->assign(['uniqueIdentifier' => $this->ids->get('line-item-4')]);

        $price = new CalculatedPrice(
            100,
            100,
            new CalculatedTaxCollection([
                new CalculatedTax(
                    19,
                    19,
                    100
                ),
            ]),
            new TaxRuleCollection(),
            1
        );

        $lineItemGrandChild1->setPrice(clone $price);
        $lineItemGrandChild2->setPrice(clone $price);

        $lineItemChild = new LineItem(
            $this->ids->get('line-item-2'),
            LineItem::CONTAINER_LINE_ITEM,
            $this->ids->get('line-item-2'),
            1,
        );

        $lineItemChild->assign(['uniqueIdentifier' => $this->ids->get('line-item-2')]);

        $price = new CalculatedPrice(
            200,
            200,
            new CalculatedTaxCollection([
                new CalculatedTax(
                    38,
                    19,
                    200
                ),
            ]),
            new TaxRuleCollection(),
            1
        );

        $lineItemChild->setPrice(clone $price);
        $lineItemChild->addChild($lineItemGrandChild1);
        $lineItemChild->addChild($lineItemGrandChild2);

        $cart = $this->createCart();
        $cart->setDeliveries(new DeliveryCollection());

        $lineItem = $cart->get($this->ids->get('line-item-1'));

        static::assertNotNull($lineItem);

        // 238         > 238         > 119          +  119
        // line-item 1 > line-item-2 > (line-item-3 && line-item-4)
        $lineItem->addChild($lineItemChild);
        $lineItem->setPrice(clone $price);
        $lineItem->setType(LineItem::CONTAINER_LINE_ITEM);

        $result = new TaxProviderResult(
            [
                $this->ids->get('line-item-3') => new CalculatedTaxCollection([
                    new CalculatedTax(
                        10,
                        10,
                        100
                    ),
                ]),
            ]
        );

        $context = $this->createMock(SalesChannelContext::class);
        $context
            ->method('getTaxState')
            ->willReturn(CartPrice::TAX_STATE_NET);
        $context
            ->method('getTotalRounding')
            ->willReturn(new CashRoundingConfig(2, 0.01, true));

        $this->adjustment->adjust($cart, $result, $context);

        $cartPrice = $cart->getPrice();
        $lineItemPrice = $lineItem->getPrice();

        static::assertNotNull($lineItemPrice);

        $lineItemChild = $lineItem
            ->getChildren()
            ->get($this->ids->get('line-item-2'));

        static::assertNotNull($lineItemChild);

        $lineItemChildPrice = $lineItemChild->getPrice();

        static::assertNotNull($lineItemChildPrice);

        $lineItemGrandChild1 = $lineItemChild
            ->getChildren()
            ->get($this->ids->get('line-item-3'));

        static::assertNotNull($lineItemGrandChild1);

        $lineItemGrandChild1Price = $lineItemGrandChild1->getPrice();

        static::assertNotNull($lineItemGrandChild1Price);

        $lineItemGrandChild2 = $lineItemChild
            ->getChildren()
            ->get($this->ids->get('line-item-4'));

        static::assertNotNull($lineItemGrandChild2);

        $lineItemGrandChild2Price = $lineItemGrandChild2->getPrice();

        static::assertNotNull($lineItemGrandChild2Price);

        // 200 line-items + 38 taxes
        static::assertSame(238.0, $cartPrice->getTotalPrice());
        static::assertSame(200.0, $cartPrice->getNetPrice());

        static::assertSame(38.0, $lineItemPrice->getCalculatedTaxes()->getAmount());
        static::assertSame(200.0, $lineItemPrice->getTotalPrice());

        static::assertSame(38.0, $lineItemChildPrice->getCalculatedTaxes()->getAmount());
        static::assertSame(200.0, $lineItemChildPrice->getTotalPrice());

        static::assertSame(10.0, $lineItemGrandChild1Price->getCalculatedTaxes()->getAmount());
        static::assertSame(100.0, $lineItemGrandChild1Price->getTotalPrice());

        static::assertSame(19.0, $lineItemGrandChild2Price->getCalculatedTaxes()->getAmount());
        static::assertSame(100.0, $lineItemGrandChild2Price->getTotalPrice());

        static::assertSame(0.0, $cart->getShippingCosts()->getTotalPrice());
    }

    private function createCart(): Cart
    {
        $cart = new Cart('test');

        $lineItem = new LineItem(
            $this->ids->get('line-item-1'),
            LineItem::PRODUCT_LINE_ITEM_TYPE,
            $this->ids->get('line-item-1'),
            1,
        );

        $lineItem->assign(['uniqueIdentifier' => $this->ids->get('line-item-1')]);

        $taxes = new CalculatedTaxCollection([
            new CalculatedTax(
                10,
                10,
                100
            ),
        ]);

        $calculatedPrice = new CalculatedPrice(
            100,
            100,
            $taxes,
            new TaxRuleCollection(),
            1
        );

        $lineItem->setPrice($calculatedPrice);
        $cart->add($lineItem);

        $deliveries = new DeliveryCollection([
            new Delivery(
                new DeliveryPositionCollection([
                    new DeliveryPosition(
                        $this->ids->get('delivery-position-1'),
                        $lineItem,
                        1,
                        clone $calculatedPrice,
                        new DeliveryDate(new \DateTimeImmutable(), new \DateTimeImmutable())
                    ),
                ]),
                new DeliveryDate(new \DateTimeImmutable(), new \DateTimeImmutable()),
                new ShippingMethodEntity(),
                new ShippingLocation(new CountryEntity(), new CountryStateEntity(), null),
                clone $calculatedPrice
            ),
        ]);

        $cart->addDeliveries($deliveries);

        $calculator = new AmountCalculator(
            new CashRounding(),
            new PercentageTaxRuleBuilder(),
            new TaxCalculator()
        );

        $context = $this->createMock(SalesChannelContext::class);
        $context
            ->method('getTotalRounding')
            ->willReturn(new CashRoundingConfig(2, 0.01, true));

        // let the cart calculate with the real tax calculator to simulate previous cart calculation
        $cartPrice = $calculator->calculate(
            $cart->getLineItems()->getPrices(),
            $cart->getDeliveries()->getShippingCosts(),
            $context
        );

        $cart = new Cart('test');
        $cart->add($lineItem);
        $cart->addDeliveries($deliveries);
        $cart->setPrice($cartPrice);

        static::assertNotNull($cart->get($this->ids->get('line-item-1')));

        return $cart;
    }
}
