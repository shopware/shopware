<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Order\Aggregate\OrderLineItem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Content\Product\State;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Annotation\DisabledFeatures;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(OrderLineItemCollection::class)]
class OrderLineItemCollectionTest extends TestCase
{
    public function testFilterGoodsFlat(): void
    {
        $lineItemA = new OrderLineItemEntity();
        $lineItemA->setId(Uuid::randomHex());
        $lineItemA->setGood(true);

        $lineItemB = new OrderLineItemEntity();
        $lineItemB->setId(Uuid::randomHex());
        $lineItemB->setGood(false);

        $lineItemC = new OrderLineItemEntity();
        $lineItemC->setId(Uuid::randomHex());
        $lineItemC->setGood(true);

        $lineItemD = new OrderLineItemEntity();
        $lineItemD->setId(Uuid::randomHex());
        $lineItemD->setGood(false);

        $lineItemE = new OrderLineItemEntity();
        $lineItemE->setId(Uuid::randomHex());
        $lineItemE->setGood(true);

        $lineItemC->setChildren(new OrderLineItemCollection([$lineItemE, $lineItemD]));

        $collection = new OrderLineItemCollection([$lineItemA, $lineItemB, $lineItemC]);

        $filtered = $collection->filterGoodsFlat();

        static::assertSame([$lineItemA, $lineItemC, $lineItemE], $filtered);
    }

    public function testGetPayloadsProperty(): void
    {
        $lineItemA = new OrderLineItemEntity();
        $lineItemA->setId(Uuid::randomHex());

        $lineItemB = new OrderLineItemEntity();
        $lineItemB->setId(Uuid::randomHex());

        $collection = new OrderLineItemCollection([$lineItemA, $lineItemB]);

        static::assertSame([], $collection->getPayloadsProperty('foobar'));

        $lineItemA->setPayload(['foobar' => 'foo']);

        static::assertSame([$lineItemA->getId() => 'foo'], $collection->getPayloadsProperty('foobar'));

        $lineItemB->setPayload(['foobar' => 'bar']);

        static::assertSame([$lineItemA->getId() => 'foo', $lineItemB->getId() => 'bar'], $collection->getPayloadsProperty('foobar'));
    }

    public function testGetOrderIdsAndFilterByOrderId(): void
    {
        $first = $this->createLineItem('first');
        $first->setOrderId('order-a');

        $second = $this->createLineItem('second');
        $second->setOrderId('order-b');

        $collection = new OrderLineItemCollection([$first, $second]);

        static::assertSame(['first' => 'order-a', 'second' => 'order-b'], $collection->getOrderIds());
        static::assertSame(['first'], $collection->filterByOrderId('order-a')->getKeys());
    }

    public function testSortByCreationDateAscendingAndDescending(): void
    {
        $older = $this->createLineItem('older');
        $older->setCreatedAt(new \DateTimeImmutable('2024-01-01'));

        $newer = $this->createLineItem('newer');
        $newer->setCreatedAt(new \DateTimeImmutable('2025-01-01'));

        $collection = new OrderLineItemCollection([$newer, $older]);
        $collection->sortByCreationDate();
        static::assertSame(['older', 'newer'], $collection->getKeys());

        $collection->sortByCreationDate(FieldSorting::DESCENDING);
        static::assertSame(['newer', 'older'], $collection->getKeys());
    }

    public function testSortByPosition(): void
    {
        $second = $this->createLineItem('second');
        $second->setPosition(2);

        $first = $this->createLineItem('first');
        $first->setPosition(1);

        $collection = new OrderLineItemCollection([$second, $first]);
        $collection->sortByPosition();

        static::assertSame(['first', 'second'], $collection->getKeys());
    }

    public function testFilterByType(): void
    {
        $product = $this->createLineItem('product-item');
        $product->setType(LineItem::PRODUCT_LINE_ITEM_TYPE);

        $discount = $this->createLineItem('discount-item');
        $discount->setType(LineItem::DISCOUNT_LINE_ITEM);

        $collection = new OrderLineItemCollection([$product, $discount]);

        static::assertSame(['product-item'], $collection->filterByType(LineItem::PRODUCT_LINE_ITEM_TYPE)->getKeys());
    }

    public function testHasLineItemWithType(): void
    {
        $physical = $this->createLineItem('physical');
        $physical->setPayload([LineItem::PAYLOAD_PRODUCT_TYPE => State::IS_PHYSICAL]);

        $collection = new OrderLineItemCollection([$physical, $this->createLineItem('without-payload')]);

        static::assertTrue($collection->hasLineItemWithType(State::IS_PHYSICAL));
        static::assertFalse($collection->hasLineItemWithType(State::IS_DOWNLOAD));
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testHasLineItemWithState(): void
    {
        $download = $this->createLineItem('download');
        $download->setStates([State::IS_DOWNLOAD]);

        $collection = new OrderLineItemCollection([$download, $this->createLineItem('stateless')]);

        static::assertTrue($collection->hasLineItemWithState(State::IS_DOWNLOAD));
        static::assertFalse($collection->hasLineItemWithState(State::IS_PHYSICAL));
    }

    public function testGetPricesSkipsLineItemsWithoutPrice(): void
    {
        $priced = $this->createLineItem('priced');
        $priced->setPrice(new CalculatedPrice(10, 10, new CalculatedTaxCollection(), new TaxRuleCollection()));

        $collection = new OrderLineItemCollection([$priced, $this->createLineItem('unpriced')]);

        static::assertCount(1, $collection->getPrices());
    }

    public function testApiAlias(): void
    {
        static::assertSame('order_line_item_collection', (new OrderLineItemCollection())->getApiAlias());
    }

    private function createLineItem(string $id): OrderLineItemEntity
    {
        $lineItem = new OrderLineItemEntity();
        $lineItem->setId($id);

        return $lineItem;
    }
}
