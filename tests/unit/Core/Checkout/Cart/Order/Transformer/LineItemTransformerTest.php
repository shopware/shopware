<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Order\Transformer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Order\Transformer\LineItemTransformer;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Product\State;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[CoversClass(LineItemTransformer::class)]
class LineItemTransformerTest extends TestCase
{
    private int $position = 1;

    protected function tearDown(): void
    {
        $this->position = 1;
    }

    public function testTransformFlatToNested(): void
    {
        $containerId = Uuid::randomHex();
        $productId = Uuid::randomHex();
        $optionOneId = Uuid::randomHex();
        $optionTwoId = Uuid::randomHex();

        $orderLineItemCollection = new OrderLineItemCollection(
            [
                $this->buildOrderLineItemEntity($containerId, LineItem::CUSTOM_LINE_ITEM_TYPE, null, 3),
                $this->buildOrderLineItemEntity($productId, LineItem::PRODUCT_LINE_ITEM_TYPE, $containerId, 3),
                $this->buildOrderLineItemEntity($optionOneId, LineItem::CUSTOM_LINE_ITEM_TYPE, $containerId, 3),
                $this->buildOrderLineItemEntity($optionTwoId, LineItem::CUSTOM_LINE_ITEM_TYPE, $containerId, 3),
            ]
        );

        $nestedCollection = LineItemTransformer::transformFlatToNested($orderLineItemCollection);
        static::assertCount(1, $nestedCollection);

        $lineItem = $nestedCollection->first();
        static::assertInstanceOf(LineItem::class, $lineItem);
        static::assertSame(3, $lineItem->getQuantity());

        $childrenCollection = $lineItem->getChildren();
        static::assertCount(3, $childrenCollection);

        $productCollection = $childrenCollection->filterType(LineItem::PRODUCT_LINE_ITEM_TYPE);
        static::assertCount(1, $productCollection);

        $productLineItem = $productCollection->get($productId);
        static::assertInstanceOf(LineItem::class, $productLineItem);
        static::assertSame(3, $productLineItem->getQuantity());
    }

    public function testTransformFlatToNestedWithDimensionData(): void
    {
        $productId = Uuid::randomHex();

        $product = new ProductEntity();
        $product->setMaxPurchase(50);
        $product->setMinPurchase(10);
        $product->setPurchaseSteps(5);

        $product->setWidth(100);
        $product->setHeight(100);
        $product->setLength(100);

        $item = $this->buildOrderLineItemEntity($productId, LineItem::PRODUCT_LINE_ITEM_TYPE, null, 3);
        $item->setProduct($product);
        $item->setStates([State::IS_PHYSICAL]);

        $orderLineItemCollection = new OrderLineItemCollection(
            [
                $item,
            ]
        );

        $nestedCollection = LineItemTransformer::transformFlatToNested($orderLineItemCollection);

        $lineItem = $nestedCollection->first();
        static::assertNotNull($lineItem);

        $quantityInformation = $lineItem->getQuantityInformation();
        static::assertNotNull($quantityInformation);

        static::assertSame($product->getMinPurchase(), $quantityInformation->getMinPurchase());
        static::assertSame($product->getMaxPurchase(), $quantityInformation->getMaxPurchase());
        static::assertSame($product->getPurchaseSteps(), $quantityInformation->getPurchaseSteps());

        $deliveryInformation = $lineItem->getDeliveryInformation();

        static::assertNotNull($deliveryInformation);
        static::assertSame($product->getWidth(), $deliveryInformation->getWidth());
        static::assertSame($product->getHeight(), $deliveryInformation->getHeight());
        static::assertSame($product->getLength(), $deliveryInformation->getLength());
    }

    public function testTransformFlatToNestedWorksForDeepNesting(): void
    {
        $level1 = Uuid::randomHex();
        $level2 = Uuid::randomHex();
        $level3 = Uuid::randomHex();
        $level4 = Uuid::randomHex();

        $orderLineItemCollection = new OrderLineItemCollection(
            [
                $this->buildOrderLineItemEntity($level1, LineItem::PRODUCT_LINE_ITEM_TYPE, null),
                $this->buildOrderLineItemEntity($level2, LineItem::CUSTOM_LINE_ITEM_TYPE, $level1),
                $this->buildOrderLineItemEntity($level3, LineItem::CUSTOM_LINE_ITEM_TYPE, $level2),
                $this->buildOrderLineItemEntity($level4, LineItem::CUSTOM_LINE_ITEM_TYPE, $level3),
            ]
        );

        $nestedCollection = LineItemTransformer::transformFlatToNested($orderLineItemCollection);
        static::assertCount(1, $nestedCollection);

        $level1Item = $nestedCollection->first();
        static::assertNotNull($level1Item);
        static::assertCount(1, $level1Item->getChildren());

        $level2Item = $level1Item->getChildren()->first();
        static::assertNotNull($level2Item);
        static::assertCount(1, $level2Item->getChildren());

        $level3Item = $level2Item->getChildren()->first();
        static::assertNotNull($level3Item);
        static::assertCount(1, $level3Item->getChildren());

        $level4Item = $level3Item->getChildren()->first();
        static::assertNotNull($level4Item);
        static::assertEmpty($level4Item->getChildren());
    }

    public function testTransformFlatToNestedDoesNotAddNoneExistingParentIds(): void
    {
        $level1 = Uuid::randomHex();
        $level2 = Uuid::randomHex();
        $level3 = Uuid::randomHex();
        $noneExistingParent = Uuid::randomHex();
        $noneExistingParentChild = Uuid::randomHex();

        $orderLineItemCollection = new OrderLineItemCollection(
            [
                $this->buildOrderLineItemEntity($level1, LineItem::CUSTOM_LINE_ITEM_TYPE, null),
                $this->buildOrderLineItemEntity($level2, LineItem::PRODUCT_LINE_ITEM_TYPE, $level1),
                $this->buildOrderLineItemEntity($level3, LineItem::CUSTOM_LINE_ITEM_TYPE, $level2),
                $this->buildOrderLineItemEntity($noneExistingParent, LineItem::PRODUCT_LINE_ITEM_TYPE, Uuid::randomHex()),
                $this->buildOrderLineItemEntity($noneExistingParentChild, LineItem::CUSTOM_LINE_ITEM_TYPE, $noneExistingParent),
            ]
        );

        $nestedCollection = LineItemTransformer::transformFlatToNested($orderLineItemCollection);
        static::assertCount(1, $nestedCollection);

        $level1Item = $nestedCollection->first();
        static::assertNotNull($level1Item);
        static::assertCount(1, $level1Item->getChildren());

        $level2Item = $level1Item->getChildren()->first();
        static::assertNotNull($level2Item);
        static::assertCount(1, $level2Item->getChildren());

        $level3Item = $level2Item->getChildren()->first();
        static::assertNotNull($level3Item);
        static::assertEmpty($level3Item->getChildren());

        $allLineItems = $nestedCollection->getFlat();
        static::assertCount(3, $allLineItems);
        foreach ($allLineItems as $item) {
            static::assertNotEquals($noneExistingParent, $item->getId());
        }
    }

    public function testTransformFlatToNestedReturnsEmptyCollectionOnEmptyInput(): void
    {
        $orderLineItemCollection = new OrderLineItemCollection();

        $nestedCollection = LineItemTransformer::transformFlatToNested($orderLineItemCollection);
        static::assertEmpty($nestedCollection);
    }

    public function testTransformFlatToNestedAddsNestedAndFlatButNotNoneExistingParentSimultaneously(): void
    {
        $level1 = Uuid::randomHex();
        $level2 = Uuid::randomHex();
        $level3 = Uuid::randomHex();
        $product = Uuid::randomHex();
        $noneExistingParent = Uuid::randomHex();

        $orderLineItemCollection = new OrderLineItemCollection(
            [
                $this->buildOrderLineItemEntity($level1, LineItem::CUSTOM_LINE_ITEM_TYPE, null),
                $this->buildOrderLineItemEntity($level2, LineItem::PRODUCT_LINE_ITEM_TYPE, $level1),
                $this->buildOrderLineItemEntity($level3, LineItem::CUSTOM_LINE_ITEM_TYPE, $level2),
                $this->buildOrderLineItemEntity($product, LineItem::PRODUCT_LINE_ITEM_TYPE, null),
                $this->buildOrderLineItemEntity($noneExistingParent, LineItem::CUSTOM_LINE_ITEM_TYPE, Uuid::randomHex()),
            ]
        );

        $nestedCollection = LineItemTransformer::transformFlatToNested($orderLineItemCollection);
        static::assertCount(2, $nestedCollection);

        $level1Item = $nestedCollection->get($level1);
        static::assertNotNull($level1Item);
        static::assertCount(1, $level1Item->getChildren());

        $level2Item = $level1Item->getChildren()->first();
        static::assertNotNull($level2Item);
        static::assertCount(1, $level2Item->getChildren());

        $level3Item = $level2Item->getChildren()->first();
        static::assertNotNull($level3Item);
        static::assertEmpty($level3Item->getChildren());

        $productItem = $nestedCollection->get($product);
        static::assertNotNull($productItem);
        static::assertEmpty($productItem->getChildren());

        $allLineItems = $nestedCollection->getFlat();
        static::assertCount(4, $allLineItems);
        foreach ($allLineItems as $item) {
            static::assertNotEquals($noneExistingParent, $item->getId());
        }
    }

    private function buildOrderLineItemEntity(string $id, string $type, ?string $parentId, int $quantity = 1): OrderLineItemEntity
    {
        $orderLineItemEntity = new OrderLineItemEntity();
        $orderLineItemEntity->setId($id);
        $orderLineItemEntity->setType($type);
        $orderLineItemEntity->setPosition($this->position === 1 ? $this->position : ++$this->position);
        $orderLineItemEntity->setIdentifier($id);
        $orderLineItemEntity->setLabel(Uuid::randomHex());
        $orderLineItemEntity->setGood(true);
        $orderLineItemEntity->setRemovable(true);
        $orderLineItemEntity->setStackable(false);
        $orderLineItemEntity->setQuantity($quantity);

        if ($parentId === null) {
            return $orderLineItemEntity;
        }

        $orderLineItemEntity->setParentId($parentId);

        return $orderLineItemEntity;
    }
}
