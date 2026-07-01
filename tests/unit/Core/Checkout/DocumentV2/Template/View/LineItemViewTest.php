<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\DocumentV2\Template\View;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTax;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\DocumentV2\DocumentV2Exception;
use Shopware\Core\Checkout\DocumentV2\Template\Enum\TaxCategory;
use Shopware\Core\Checkout\DocumentV2\Template\View\LineItemView;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(LineItemView::class)]
class LineItemViewTest extends TestCase
{
    public function testListFromOrderNumbersProductLineItemsAndComputesNet(): void
    {
        $order = $this->createOrder(CartPrice::TAX_STATE_GROSS, [
            $this->createProductLineItem('p-1', 'Widget', quantity: 2, total: 119.0, tax: 19.0, rate: 19.0),
        ]);

        $views = LineItemView::listFromOrder($order);

        static::assertCount(1, $views);

        $view = $views[0];
        static::assertSame('1', $view->lineId);
        static::assertSame('Widget', $view->name);
        static::assertSame(2.0, $view->quantity);
        static::assertSame(50.0, $view->netUnitPrice);
        static::assertSame(100.0, $view->lineTotal);
        static::assertSame(TaxCategory::STANDARD_RATE, $view->taxCategory);
        static::assertSame(19.0, $view->taxRate);
    }

    public function testListFromOrderKeepsNetPriceUnchangedWhenCartIsNet(): void
    {
        $order = $this->createOrder(CartPrice::TAX_STATE_NET, [
            $this->createProductLineItem('p-1', 'Widget', tax: 19.0, rate: 19.0),
        ]);

        $view = LineItemView::listFromOrder($order)[0];

        static::assertSame(100.0, $view->lineTotal);
        static::assertSame(100.0, $view->netUnitPrice);
    }

    public function testListFromOrderUsesZeroRatedForZeroTaxRate(): void
    {
        $order = $this->createOrder(CartPrice::TAX_STATE_NET, [
            $this->createProductLineItem('p-1', 'Free Sample', total: 0.0),
        ]);

        $view = LineItemView::listFromOrder($order)[0];

        static::assertSame(TaxCategory::ZERO_RATED, $view->taxCategory);
        static::assertSame(0.0, $view->taxRate);
    }

    public function testListFromOrderSkipsPromotionAndCreditLineItems(): void
    {
        $order = $this->createOrder(CartPrice::TAX_STATE_NET, [
            $this->createProductLineItem('p-1', 'Widget'),
            $this->createLineItemOfType(LineItem::PROMOTION_LINE_ITEM_TYPE, 'PROMO'),
            $this->createLineItemOfType(LineItem::CREDIT_LINE_ITEM_TYPE, 'Credit'),
        ]);

        $views = LineItemView::listFromOrder($order);

        static::assertCount(1, $views);
        static::assertSame('1', $views[0]->lineId);
    }

    public function testListFromOrderIncludesCustomLineItems(): void
    {
        $custom = $this->createLineItemOfType(LineItem::CUSTOM_LINE_ITEM_TYPE, 'Custom');
        $custom->setPrice(new CalculatedPrice(
            10.0,
            10.0,
            new CalculatedTaxCollection(),
            new TaxRuleCollection(),
        ));

        $order = $this->createOrder(CartPrice::TAX_STATE_NET, [$custom]);

        $views = LineItemView::listFromOrder($order);

        static::assertCount(1, $views);
        static::assertSame('Custom', $views[0]->name);
    }

    public function testListFromOrderIncludesNestedProductLineItems(): void
    {
        $container = $this->createLineItemOfType(LineItem::CONTAINER_LINE_ITEM, 'Bundle');
        $container->setChildren(new OrderLineItemCollection([
            $this->createProductLineItem('p-child', 'Nested widget', position: 2),
        ]));

        $views = LineItemView::listFromOrder($this->createOrder(CartPrice::TAX_STATE_NET, [$container]));

        static::assertCount(1, $views);
        static::assertSame('1-2', $views[0]->lineId);
        static::assertSame('Nested widget', $views[0]->name);
    }

    public function testListFromOrderKeepsFullParentPositionPathForDeeplyNestedLineItems(): void
    {
        $child = $this->createLineItemOfType(LineItem::CONTAINER_LINE_ITEM, 'Nested bundle', position: 2);
        $child->setChildren(new OrderLineItemCollection([
            $this->createProductLineItem('p-grand-child', 'Deep widget', position: 3),
        ]));

        $container = $this->createLineItemOfType(LineItem::CONTAINER_LINE_ITEM, 'Bundle');
        $container->setChildren(new OrderLineItemCollection([$child]));

        $views = LineItemView::listFromOrder($this->createOrder(CartPrice::TAX_STATE_NET, [$container]));

        static::assertCount(1, $views);
        static::assertSame('1-2-3', $views[0]->lineId);
    }

    public function testListFromOrderCarriesProductMetadata(): void
    {
        $product = new ProductEntity();
        $product->setUniqueIdentifier(Uuid::randomHex());
        $product->setProductNumber('SKU-1');
        $product->setEan('1234567890123');
        $product->setPurchaseUnit(2.5);

        $lineItem = $this->createProductLineItem('p-1', 'Widget');
        $lineItem->setProduct($product);

        $view = LineItemView::listFromOrder($this->createOrder(CartPrice::TAX_STATE_NET, [$lineItem]))[0];

        static::assertSame('SKU-1', $view->productNumber);
        static::assertSame('1234567890123', $view->ean);
        static::assertSame(2.5, $view->basisQuantity);
    }

    public function testListFromOrderDefaultsBasisQuantityWhenPurchaseUnitMissing(): void
    {
        $product = new ProductEntity();
        $product->setUniqueIdentifier(Uuid::randomHex());
        $product->setProductNumber('SKU-NO-PU');

        $lineItem = $this->createProductLineItem('p-1', 'Widget');
        $lineItem->setProduct($product);

        $view = LineItemView::listFromOrder($this->createOrder(CartPrice::TAX_STATE_NET, [$lineItem]))[0];

        static::assertSame(LineItemView::DEFAULT_BASIS_QUANTITY, $view->basisQuantity);
    }

    public function testListFromOrderThrowsOnNonPositiveQuantity(): void
    {
        $item = $this->createLineItemOfType(LineItem::PRODUCT_LINE_ITEM_TYPE, 'Widget', 'p-1');
        $item->setQuantity(0);
        $item->setPrice(new CalculatedPrice(
            10.0,
            0.0,
            new CalculatedTaxCollection([new CalculatedTax(0.0, 19.0, 0.0)]),
            new TaxRuleCollection(),
        ));

        $order = $this->createOrder(CartPrice::TAX_STATE_NET, [$item]);

        static::expectExceptionObject(DocumentV2Exception::invalidOrderData(
            $order->getId(),
            'lineItem.quantity',
            'Line item "p-1" has non-positive quantity 0.',
        ));

        LineItemView::listFromOrder($order);
    }

    /**
     * @param list<OrderLineItemEntity> $lineItems
     */
    private function createOrder(string $taxState, array $lineItems): OrderEntity
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
        $order->setLineItems(new OrderLineItemCollection($lineItems));

        return $order;
    }

    private function createProductLineItem(
        string $identifier,
        string $label,
        int $quantity = 1,
        float $total = 100.0,
        float $tax = 0.0,
        float $rate = 0.0,
        int $position = 1,
    ): OrderLineItemEntity {
        $item = $this->createLineItemOfType(LineItem::PRODUCT_LINE_ITEM_TYPE, $label, $identifier, $position);
        $item->setQuantity($quantity);
        $item->setPrice(new CalculatedPrice(
            $total / max($quantity, 1),
            $total,
            new CalculatedTaxCollection([new CalculatedTax($tax, $rate, $total)]),
            new TaxRuleCollection(),
            $quantity,
        ));

        return $item;
    }

    private function createLineItemOfType(
        string $type,
        string $label,
        string $identifier = 'item',
        int $position = 1,
    ): OrderLineItemEntity {
        $item = new OrderLineItemEntity();
        $item->setUniqueIdentifier(Uuid::randomHex());
        $item->setIdentifier($identifier);
        $item->setType($type);
        $item->setLabel($label);
        $item->setQuantity(1);
        $item->setPosition($position);

        return $item;
    }
}
