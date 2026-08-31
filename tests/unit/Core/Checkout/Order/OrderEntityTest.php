<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Order;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(OrderEntity::class)]
class OrderEntityTest extends TestCase
{
    public function testGetNestedLineItemsReturnsNullWhenNoLineItemsSet(): void
    {
        $order = new OrderEntity();

        static::assertNull($order->getNestedLineItems());
    }

    public function testGetNestedLineItemsReturnsRootsSortedByPosition(): void
    {
        $rootB = $this->createLineItem('root-b', null, 2);
        $rootA = $this->createLineItem('root-a', null, 1);
        $child = $this->createLineItem('child-of-a', 'root-a', 1);

        $order = new OrderEntity();
        $order->setLineItems(new OrderLineItemCollection([$rootB, $rootA, $child]));

        $nested = $order->getNestedLineItems();
        static::assertInstanceOf(OrderLineItemCollection::class, $nested);

        $roots = array_values($nested->getElements());
        static::assertCount(2, $roots);
        static::assertSame('root-a', $roots[0]->getId());
        static::assertSame('root-b', $roots[1]->getId());
    }

    public function testGetNestedLineItemsNestsChildrenUnderCorrectParent(): void
    {
        $root = $this->createLineItem('root', null, 1);
        $childSecond = $this->createLineItem('child-2', 'root', 2);
        $childFirst = $this->createLineItem('child-1', 'root', 1);
        $grandChild = $this->createLineItem('grandchild', 'child-1', 1);

        $order = new OrderEntity();
        $order->setLineItems(new OrderLineItemCollection([$root, $childSecond, $childFirst, $grandChild]));

        $nested = $order->getNestedLineItems();
        static::assertInstanceOf(OrderLineItemCollection::class, $nested);
        static::assertCount(1, $nested);

        $rootResult = $nested->first();
        static::assertInstanceOf(OrderLineItemEntity::class, $rootResult);

        $children = $rootResult->getChildren();
        static::assertInstanceOf(OrderLineItemCollection::class, $children);

        $childList = array_values($children->getElements());
        static::assertCount(2, $childList);
        static::assertSame('child-1', $childList[0]->getId());
        static::assertSame('child-2', $childList[1]->getId());

        $grandChildren = $childList[0]->getChildren();
        static::assertInstanceOf(OrderLineItemCollection::class, $grandChildren);
        static::assertCount(1, $grandChildren);
        static::assertSame('grandchild', $grandChildren->first()?->getId());
    }

    public function testLineItemsGetterAndSetter(): void
    {
        $collection = new OrderLineItemCollection([$this->createLineItem('id', null, 1)]);

        $order = new OrderEntity();
        $order->setLineItems($collection);

        static::assertSame($collection, $order->getLineItems());
    }

    private function createLineItem(string $id, ?string $parentId, int $position): OrderLineItemEntity
    {
        $lineItem = new OrderLineItemEntity();
        $lineItem->setId($id);
        $lineItem->setPosition($position);

        if ($parentId !== null) {
            $lineItem->setParentId($parentId);
        }

        return $lineItem;
    }
}
