<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\DocumentV2\Template\Calculation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTax;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\DocumentV2\Template\Calculation\OrderInverter;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(OrderInverter::class)]
class OrderInverterTest extends TestCase
{
    public function testInvertNegatesQuantitiesAndTotalsWhileKeepingUnitPricePositive(): void
    {
        $lineItem = $this->createLineItem(quantity: 2, totalPrice: 100.0, unitPrice: 50.0);
        $order = $this->createOrder([$lineItem]);

        OrderInverter::invert($order);

        static::assertSame(-2, $lineItem->getQuantity());
        static::assertSame(-100.0, $lineItem->getTotalPrice());

        $price = $lineItem->getPrice();
        static::assertNotNull($price);
        static::assertSame(50.0, $price->getUnitPrice());
        static::assertSame(-100.0, $price->getTotalPrice());

        $tax = $price->getCalculatedTaxes()->first();
        static::assertNotNull($tax);
        static::assertSame(-19.0, $tax->getTax());
        static::assertSame(-100.0, $tax->getPrice());
    }

    public function testInvertNegatesOrderTotalsTaxesAndShipping(): void
    {
        $order = $this->createOrder([$this->createLineItem(quantity: 2, totalPrice: 100.0, unitPrice: 50.0)]);

        OrderInverter::invert($order);

        static::assertSame(-100.0, $order->getAmountNet());
        static::assertSame(-119.0, $order->getAmountTotal());
        static::assertSame(-10.0, $order->getShippingTotal());

        $price = $order->getPrice();
        static::assertSame(-100.0, $price->getNetPrice());
        static::assertSame(-119.0, $price->getTotalPrice());
        static::assertSame(-100.0, $price->getPositionPrice());

        $orderTax = $price->getCalculatedTaxes()->first();
        static::assertNotNull($orderTax);
        static::assertSame(-19.0, $orderTax->getTax());

        $delivery = $order->getDeliveries()?->first();
        static::assertNotNull($delivery);
        static::assertSame(-10.0, $delivery->getShippingCosts()->getTotalPrice());
    }

    public function testInvertRecursesIntoChildLineItems(): void
    {
        $child = $this->createLineItem(quantity: 3, totalPrice: 30.0, unitPrice: 10.0);
        $parent = $this->createLineItem(quantity: 1, totalPrice: 30.0, unitPrice: 30.0);
        $parent->setChildren(new OrderLineItemCollection([$child]));

        $order = $this->createOrder([$parent]);

        OrderInverter::invert($order);

        static::assertSame(-3, $child->getQuantity());
        static::assertSame(-30.0, $child->getTotalPrice());
        static::assertSame(10.0, $child->getPrice()?->getUnitPrice());
    }

    /**
     * @param list<OrderLineItemEntity> $lineItems
     */
    private function createOrder(array $lineItems): OrderEntity
    {
        $order = new OrderEntity();
        $order->setId(Uuid::randomHex());
        $order->setAmountNet(100.0);
        $order->setAmountTotal(119.0);
        $order->setShippingTotal(10.0);
        $order->setPrice(new CartPrice(
            100.0,
            119.0,
            100.0,
            new CalculatedTaxCollection([new CalculatedTax(19.0, 19.0, 100.0)]),
            new TaxRuleCollection(),
            CartPrice::TAX_STATE_NET,
        ));

        $delivery = new OrderDeliveryEntity();
        $delivery->setUniqueIdentifier(Uuid::randomHex());
        $delivery->setShippingCosts(new CalculatedPrice(
            10.0,
            10.0,
            new CalculatedTaxCollection(),
            new TaxRuleCollection(),
        ));
        $order->setDeliveries(new OrderDeliveryCollection([$delivery]));

        $order->setLineItems(new OrderLineItemCollection($lineItems));

        return $order;
    }

    private function createLineItem(int $quantity, float $totalPrice, float $unitPrice): OrderLineItemEntity
    {
        $lineItem = new OrderLineItemEntity();
        $lineItem->setUniqueIdentifier(Uuid::randomHex());
        $lineItem->setId(Uuid::randomHex());
        $lineItem->setType(LineItem::PRODUCT_LINE_ITEM_TYPE);
        $lineItem->setIdentifier('product-' . $quantity);
        $lineItem->setLabel('Product');
        $lineItem->setQuantity($quantity);
        $lineItem->setTotalPrice($totalPrice);
        $lineItem->setPrice(new CalculatedPrice(
            $unitPrice,
            $totalPrice,
            new CalculatedTaxCollection([new CalculatedTax(19.0, 19.0, $totalPrice)]),
            new TaxRuleCollection(),
            $quantity,
        ));

        return $lineItem;
    }
}
