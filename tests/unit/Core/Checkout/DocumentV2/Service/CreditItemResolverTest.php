<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\DocumentV2\Service;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTax;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\DocumentV2\DocumentV2Exception;
use Shopware\Core\Checkout\DocumentV2\Service\CreditItemResolver;
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
#[CoversClass(CreditItemResolver::class)]
class CreditItemResolverTest extends TestCase
{
    public function testApplyInvertsCreditPricesToPositiveAndRecomputesTotals(): void
    {
        $credit = $this->createCreditItem(Uuid::randomHex(), unitPrice: -10.0, totalPrice: -10.0, tax: -1.9);
        $order = $this->createOrder([$credit]);

        $this->createResolver()->apply($order, null);

        static::assertSame(10.0, $credit->getUnitPrice());
        static::assertSame(10.0, $credit->getTotalPrice());

        $price = $credit->getPrice();

        static::assertNotNull($price);
        static::assertSame(10.0, $price->getUnitPrice());
        static::assertSame(10.0, $price->getTotalPrice());
        static::assertSame(1.9, $price->getCalculatedTaxes()->first()?->getTax());

        static::assertSame(10.0, $order->getAmountNet());
        static::assertSame(11.9, $order->getAmountTotal());
        static::assertSame(0.0, $order->getShippingTotal());
    }

    public function testApplyReducesTheOrderToTheCreditItemsOnly(): void
    {
        $credit = $this->createCreditItem(Uuid::randomHex(), unitPrice: -5.0, totalPrice: -5.0, tax: -0.95);
        $product = $this->createProductItem();
        $order = $this->createOrder([$product, $credit]);

        $this->createResolver()->apply($order, null);

        $lineItems = $order->getLineItems();
        static::assertNotNull($lineItems);
        static::assertCount(1, $lineItems);
        static::assertSame($credit, $lineItems->first());
    }

    public function testApplyKeepsDeliveriesSoIntraCommunityDetectionStillWorks(): void
    {
        $order = $this->createOrder([$this->createCreditItem(Uuid::randomHex(), -5.0, -5.0, -0.95)]);

        $this->createResolver()->apply($order, null);

        static::assertCount(1, $order->getDeliveries() ?? new OrderDeliveryCollection());
    }

    public function testApplyThrowsWhenTheOrderHasNoCreditItems(): void
    {
        $order = $this->createOrder([$this->createProductItem()]);

        $this->expectExceptionObject(DocumentV2Exception::noCreditItems($order->getId()));

        $this->createResolver()->apply($order, null);
    }

    public function testApplyThrowsWhenAllCreditItemsWereAlreadyProcessed(): void
    {
        $creditId = Uuid::randomHex();
        $order = $this->createOrder([$this->createCreditItem($creditId, -10.0, -10.0, -1.9)]);

        $resolver = $this->createResolver([Uuid::fromHexToBytes($creditId)]);

        $this->expectExceptionObject(DocumentV2Exception::noUnprocessedCreditItems($order->getId()));

        $resolver->apply($order, Uuid::randomHex());
    }

    public function testApplyExcludesCreditItemsAlreadyCreditedOnAPreviousCreditNote(): void
    {
        $keptId = Uuid::randomHex();
        $creditedId = Uuid::randomHex();
        $order = $this->createOrder([
            $this->createCreditItem($keptId, -10.0, -10.0, -1.9),
            $this->createCreditItem($creditedId, -5.0, -5.0, -0.95),
        ]);

        $connection = static::createStub(Connection::class);
        $connection->method('fetchFirstColumn')->willReturnOnConsecutiveCalls([], [Uuid::fromHexToBytes($creditedId)]);

        (new CreditItemResolver($connection))->apply($order, Uuid::randomHex());

        $lineItems = $order->getLineItems();
        static::assertNotNull($lineItems);
        static::assertSame([$keptId], array_values($lineItems->map(static fn (OrderLineItemEntity $item): string => $item->getId())));
    }

    /**
     * @param list<string> $excludedBinaryIds
     */
    private function createResolver(array $excludedBinaryIds = []): CreditItemResolver
    {
        $connection = static::createStub(Connection::class);
        $connection->method('fetchFirstColumn')->willReturn($excludedBinaryIds);

        return new CreditItemResolver($connection);
    }

    /**
     * @param list<OrderLineItemEntity> $lineItems
     */
    private function createOrder(array $lineItems, string $taxState = CartPrice::TAX_STATE_NET): OrderEntity
    {
        $order = new OrderEntity();
        $order->setId(Uuid::randomHex());
        $order->setShippingTotal(10.0);
        $order->setAmountNet(0.0);
        $order->setAmountTotal(0.0);
        $order->setPrice(new CartPrice(
            0.0,
            0.0,
            0.0,
            new CalculatedTaxCollection(),
            new TaxRuleCollection(),
            $taxState,
        ));

        $delivery = new OrderDeliveryEntity();
        $delivery->setUniqueIdentifier(Uuid::randomHex());
        $delivery->setShippingCosts(new CalculatedPrice(10.0, 10.0, new CalculatedTaxCollection(), new TaxRuleCollection()));
        $order->setDeliveries(new OrderDeliveryCollection([$delivery]));

        $order->setLineItems(new OrderLineItemCollection($lineItems));

        return $order;
    }

    private function createCreditItem(string $id, float $unitPrice, float $totalPrice, float $tax): OrderLineItemEntity
    {
        $item = new OrderLineItemEntity();
        $item->setUniqueIdentifier(Uuid::randomHex());
        $item->setId($id);
        $item->setType(LineItem::CREDIT_LINE_ITEM_TYPE);
        $item->setIdentifier('credit-' . $id);
        $item->setLabel('Voucher');
        $item->setPosition(1);
        $item->setQuantity(1);
        $item->setUnitPrice($unitPrice);
        $item->setTotalPrice($totalPrice);
        $item->setPrice(new CalculatedPrice(
            $unitPrice,
            $totalPrice,
            new CalculatedTaxCollection([new CalculatedTax($tax, 19.0, $totalPrice)]),
            new TaxRuleCollection(),
            1,
        ));

        return $item;
    }

    private function createProductItem(): OrderLineItemEntity
    {
        $item = new OrderLineItemEntity();
        $item->setUniqueIdentifier(Uuid::randomHex());
        $item->setId(Uuid::randomHex());
        $item->setType(LineItem::PRODUCT_LINE_ITEM_TYPE);
        $item->setIdentifier('product-1');
        $item->setLabel('Product');
        $item->setPosition(1);
        $item->setQuantity(1);
        $item->setUnitPrice(20.0);
        $item->setTotalPrice(20.0);
        $item->setPrice(new CalculatedPrice(
            20.0,
            20.0,
            new CalculatedTaxCollection([new CalculatedTax(3.8, 19.0, 20.0)]),
            new TaxRuleCollection(),
            1,
        ));

        return $item;
    }
}
