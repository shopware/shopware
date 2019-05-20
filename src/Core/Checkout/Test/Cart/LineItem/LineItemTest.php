<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\LineItem;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Exception\InvalidChildQuantityException;
use Shopware\Core\Checkout\Cart\Exception\InvalidQuantityException;
use Shopware\Core\Checkout\Cart\Exception\LineItemNotStackableException;
use Shopware\Core\Checkout\Cart\Exception\MixedLineItemTypeException;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;

class LineItemTest extends TestCase
{
    /**
     * @throws InvalidQuantityException
     */
    public function testCreateLineItem(): void
    {
        $lineItem = new LineItem('A', 'type');

        static::assertEquals('A', $lineItem->getId());
        static::assertEquals('type', $lineItem->getType());
        static::assertEquals(1, $lineItem->getQuantity());
    }

    /**
     * @throws InvalidQuantityException
     */
    public function testCreateLineItemWithInvalidQuantity(): void
    {
        $this->expectException(InvalidQuantityException::class);
        new LineItem('A', 'type', null, -1);
    }

    /**
     * @throws InvalidQuantityException
     * @throws LineItemNotStackableException
     */
    public function testChangeLineItemToInvalidQuantity(): void
    {
        $this->expectException(InvalidQuantityException::class);
        $lineItem = new LineItem('A', 'type');
        $lineItem->setQuantity(0);
    }

    /**
     * @throws InvalidQuantityException
     * @throws LineItemNotStackableException
     */
    public function testChangeLineItemQuantity(): void
    {
        $lineItem = new LineItem('A', 'type');
        $lineItem->setStackable(true);
        $lineItem->setQuantity(5);
        static::assertEquals(5, $lineItem->getQuantity());
    }

    /**
     * @throws InvalidQuantityException
     * @throws LineItemNotStackableException
     */
    public function testChangeNonStackableLineItemQuantity(): void
    {
        $this->expectException(LineItemNotStackableException::class);
        $lineItem = new LineItem('A', 'type');
        $lineItem->setStackable(false);
        $lineItem->setQuantity(5);
        static::assertEquals(1, $lineItem->getQuantity());
    }

    /**
     * @throws InvalidChildQuantityException
     * @throws InvalidQuantityException
     * @throws LineItemNotStackableException
     */
    public function testChangeQuantityOfParentLineItem(): void
    {
        $lineItem = (new LineItem('A', 'type'))->setStackable(true);

        $child1 = (new LineItem('A.1', 'child', null, 3))->setStackable(true);
        $child2 = (new LineItem('A.2', 'child', null, 2))->setStackable(true);
        $child3 = (new LineItem('A.3', 'child'))->setStackable(true);

        $child4 = (new LineItem('A.3.1', 'child', null, 5))->setStackable(true);
        $child5 = (new LineItem('A.3.2', 'child', null, 10))->setStackable(true);

        $child3->setChildren(new LineItemCollection([$child4, $child5]));

        $lineItem->setChildren(new LineItemCollection([$child1, $child2, $child3]));

        $lineItem->setQuantity(2);

        static::assertEquals(2, $lineItem->getQuantity());
        static::assertEquals(6, $child1->getQuantity());
        static::assertEquals(4, $child2->getQuantity());
        static::assertEquals(2, $child3->getQuantity());
        static::assertEquals(10, $child4->getQuantity());
        static::assertEquals(20, $child5->getQuantity());

        $lineItem->setQuantity(3);

        static::assertEquals(3, $lineItem->getQuantity());
        static::assertEquals(9, $child1->getQuantity());
        static::assertEquals(6, $child2->getQuantity());
        static::assertEquals(3, $child3->getQuantity());
        static::assertEquals(15, $child4->getQuantity());
        static::assertEquals(30, $child5->getQuantity());

        $lineItem->setQuantity(1);

        static::assertEquals(1, $lineItem->getQuantity());
        static::assertEquals(3, $child1->getQuantity());
        static::assertEquals(2, $child2->getQuantity());
        static::assertEquals(1, $child3->getQuantity());
        static::assertEquals(5, $child4->getQuantity());
        static::assertEquals(10, $child5->getQuantity());
    }

    /**
     * @throws InvalidChildQuantityException
     * @throws InvalidQuantityException
     * @throws LineItemNotStackableException
     */
    public function testChangeQuantityOfParentLineItemWithNonStackableChildren(): void
    {
        $lineItem = new LineItem('A', 'type');

        $child1 = new LineItem('A.1', 'child', null, 3);
        $child2 = new LineItem('A.2', 'child', null, 2);
        $child2->setStackable(false);
        $child3 = new LineItem('A.3', 'child');
        $child3->setStackable(false);

        $child4 = new LineItem('A.3.1', 'child', null, 5);
        $child5 = new LineItem('A.3.2', 'child', null, 10);

        $child3->setChildren(new LineItemCollection([$child4, $child5]));

        $lineItem->setChildren(new LineItemCollection([$child1, $child2, $child3]));

        $this->expectException(LineItemNotStackableException::class);

        $lineItem->setQuantity(2);

        static::assertEquals(2, $lineItem->getQuantity());
        static::assertEquals(6, $child1->getQuantity());
        static::assertEquals(2, $child2->getQuantity());
        static::assertEquals(1, $child3->getQuantity());
        static::assertEquals(5, $child4->getQuantity());
        static::assertEquals(10, $child5->getQuantity());

        $lineItem->setQuantity(3);

        static::assertEquals(3, $lineItem->getQuantity());
        static::assertEquals(9, $child1->getQuantity());
        static::assertEquals(2, $child2->getQuantity());
        static::assertEquals(1, $child3->getQuantity());
        static::assertEquals(5, $child4->getQuantity());
        static::assertEquals(10, $child5->getQuantity());

        $lineItem->setQuantity(1);

        static::assertEquals(1, $lineItem->getQuantity());
        static::assertEquals(3, $child1->getQuantity());
        static::assertEquals(2, $child2->getQuantity());
        static::assertEquals(1, $child3->getQuantity());
        static::assertEquals(5, $child4->getQuantity());
        static::assertEquals(10, $child5->getQuantity());
    }

    /**
     * @throws InvalidChildQuantityException
     * @throws MixedLineItemTypeException
     * @throws InvalidQuantityException
     */
    public function testAddChildrenToLineItemWithInvalidQuantity(): void
    {
        $lineItem = new LineItem('A', 'type', null, 15);

        $child1 = new LineItem('A.1', 'child', null, 3);
        $child2 = new LineItem('A.2', 'child', null, 2);
        $child3 = new LineItem('A.3', 'child');

        $this->expectException(InvalidChildQuantityException::class);

        $lineItem->addChild($child1);
        $lineItem->addChild($child2);
        $lineItem->addChild($child3);
    }

    /**
     * @throws InvalidChildQuantityException
     * @throws MixedLineItemTypeException
     * @throws InvalidQuantityException
     */
    public function testSetChildrenToLineItemWithInvalidQuantity(): void
    {
        $lineItem = new LineItem('A', 'type', null, 15);

        $child1 = new LineItem('A.1', 'child', null, 3);
        $child2 = new LineItem('A.2', 'child', null, 2);
        $child3 = new LineItem('A.3', 'child');

        $this->expectException(InvalidChildQuantityException::class);

        $lineItem->setChildren(new LineItemCollection([$child1, $child2, $child3]));
    }
}
