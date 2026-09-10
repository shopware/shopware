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
use Shopware\Core\Checkout\DocumentV2\Template\Calculation\CreditOrderReducer;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(CreditOrderReducer::class)]
class CreditOrderReducerTest extends TestCase
{
    public function testReduceFlipsStoredNegativeCreditPricesToPositive(): void
    {
        $credit = $this->createCreditItem(totalPrice: -119.0, unitPrice: -119.0, tax: -19.0, rate: 19.0);
        $order = $this->createOrder(CartPrice::TAX_STATE_GROSS);

        CreditOrderReducer::reduce($order, new OrderLineItemCollection([$credit]));

        static::assertSame(119.0, $credit->getUnitPrice());
        static::assertSame(119.0, $credit->getTotalPrice());

        $price = $credit->getPrice();
        static::assertNotNull($price);
        static::assertSame(119.0, $price->getTotalPrice());

        $tax = $price->getCalculatedTaxes()->first();
        static::assertNotNull($tax);
        static::assertSame(19.0, $tax->getTax());
        static::assertSame(119.0, $tax->getPrice());
    }

    public function testReduceReplacesOrderLineItemsWithTheCreditItemsAndZeroesShipping(): void
    {
        $credit = $this->createCreditItem(totalPrice: -119.0, unitPrice: -119.0, tax: -19.0, rate: 19.0);
        $order = $this->createOrder(CartPrice::TAX_STATE_GROSS);
        $order->setShippingTotal(9.99);

        CreditOrderReducer::reduce($order, new OrderLineItemCollection([$credit]));

        $lineItems = $order->getLineItems();
        static::assertNotNull($lineItems);
        static::assertCount(1, $lineItems);
        static::assertSame($credit, $lineItems->first());
        static::assertSame(0.0, $order->getShippingTotal());
    }

    public function testReduceRecomputesTotalsFromTheGrossBranch(): void
    {
        $credit = $this->createCreditItem(totalPrice: -119.0, unitPrice: -119.0, tax: -19.0, rate: 19.0);
        $order = $this->createOrder(CartPrice::TAX_STATE_GROSS);

        CreditOrderReducer::reduce($order, new OrderLineItemCollection([$credit]));

        // gross: netPrice = total - tax
        static::assertSame(100.0, $order->getAmountNet());
        static::assertSame(119.0, $order->getAmountTotal());

        $price = $order->getPrice();
        static::assertSame(100.0, $price->getNetPrice());
        static::assertSame(119.0, $price->getTotalPrice());
        static::assertSame(119.0, $price->getPositionPrice());
        static::assertSame(CartPrice::TAX_STATE_GROSS, $price->getTaxStatus());
    }

    public function testReduceRecomputesTotalsFromTheNetBranch(): void
    {
        $credit = $this->createCreditItem(totalPrice: -100.0, unitPrice: -100.0, tax: -19.0, rate: 19.0);
        $order = $this->createOrder(CartPrice::TAX_STATE_NET);

        CreditOrderReducer::reduce($order, new OrderLineItemCollection([$credit]));

        // net: totalPrice = net + tax
        static::assertSame(100.0, $order->getAmountNet());
        static::assertSame(119.0, $order->getAmountTotal());

        $price = $order->getPrice();
        static::assertSame(100.0, $price->getNetPrice());
        static::assertSame(119.0, $price->getTotalPrice());
        static::assertSame(CartPrice::TAX_STATE_NET, $price->getTaxStatus());
    }

    public function testReduceTreatsTaxFreeOrdersLikeTheNetBranch(): void
    {
        $credit = $this->createCreditItem(totalPrice: -50.0, unitPrice: -50.0, tax: 0.0, rate: 0.0);
        $order = $this->createOrder(CartPrice::TAX_STATE_FREE);

        CreditOrderReducer::reduce($order, new OrderLineItemCollection([$credit]));

        static::assertSame(50.0, $order->getAmountNet());
        static::assertSame(50.0, $order->getAmountTotal());
    }

    public function testReduceSumsEveryTaxRowOfAMultiRateCredit(): void
    {
        $credit = $this->createCreditItem(totalPrice: -200.0, unitPrice: -200.0, tax: 0.0, rate: 0.0);
        $credit->getPrice()?->getCalculatedTaxes()->clear();
        $credit->getPrice()?->getCalculatedTaxes()->add(new CalculatedTax(-19.0, 19.0, -100.0));
        $credit->getPrice()?->getCalculatedTaxes()->add(new CalculatedTax(-7.0, 7.0, -100.0));

        $order = $this->createOrder(CartPrice::TAX_STATE_NET);

        CreditOrderReducer::reduce($order, new OrderLineItemCollection([$credit]));

        // net branch: total = net(200) + tax(19 + 7)
        static::assertSame(200.0, $order->getAmountNet());
        static::assertSame(226.0, $order->getAmountTotal());
    }

    public function testReduceDerivesBranchAndStampFromTheSameTaxStatus(): void
    {
        $credit = $this->createCreditItem(totalPrice: -119.0, unitPrice: -119.0, tax: -19.0, rate: 19.0);

        $order = $this->createOrder(CartPrice::TAX_STATE_NET);
        $order->setTaxStatus(CartPrice::TAX_STATE_GROSS);

        CreditOrderReducer::reduce($order, new OrderLineItemCollection([$credit]));

        $price = $order->getPrice();
        static::assertSame(CartPrice::TAX_STATE_GROSS, $price->getTaxStatus());
        static::assertSame(100.0, $price->getNetPrice());
        static::assertSame(119.0, $price->getTotalPrice());
    }

    public function testReduceRecursesIntoChildCreditPrices(): void
    {
        $child = $this->createCreditItem(totalPrice: -30.0, unitPrice: -10.0, tax: -5.7, rate: 19.0);
        $parent = $this->createCreditItem(totalPrice: -30.0, unitPrice: -30.0, tax: -5.7, rate: 19.0);
        $parent->setChildren(new OrderLineItemCollection([$child]));

        $order = $this->createOrder(CartPrice::TAX_STATE_NET);

        CreditOrderReducer::reduce($order, new OrderLineItemCollection([$parent]));

        static::assertSame(30.0, $child->getTotalPrice());
        static::assertSame(10.0, $child->getUnitPrice());
        static::assertSame(30.0, $child->getPrice()?->getTotalPrice());
    }

    private function createOrder(string $taxState): OrderEntity
    {
        $order = new OrderEntity();
        $order->setId(Uuid::randomHex());
        $order->setPrice(new CartPrice(
            0.0,
            0.0,
            0.0,
            new CalculatedTaxCollection(),
            new TaxRuleCollection(),
            $taxState,
        ));

        return $order;
    }

    private function createCreditItem(
        float $totalPrice,
        float $unitPrice,
        float $tax,
        float $rate,
    ): OrderLineItemEntity {
        $item = new OrderLineItemEntity();
        $item->setUniqueIdentifier(Uuid::randomHex());
        $item->setId(Uuid::randomHex());
        $item->setType(LineItem::CREDIT_LINE_ITEM_TYPE);
        $item->setIdentifier('credit-1');
        $item->setLabel('Credit');
        $item->setPosition(1);
        $item->setQuantity(1);
        $item->setUnitPrice($unitPrice);
        $item->setTotalPrice($totalPrice);

        $item->setPrice(new CalculatedPrice(
            $unitPrice,
            $totalPrice,
            new CalculatedTaxCollection([new CalculatedTax($tax, $rate, $totalPrice)]),
            new TaxRuleCollection(),
            1,
        ));

        return $item;
    }
}
