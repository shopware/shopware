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
    public function testCreateLineItem()
    {
        $lineItem = new LineItem('A', 'type', 1);

        self::assertEquals('A', $lineItem->getKey());
        self::assertEquals('type', $lineItem->getType());
        self::assertEquals(1, $lineItem->getQuantity());
    }

    /**
     * @throws InvalidQuantityException
     */
    public function testCreateLineItemWithInvalidQuantity()
    {
        self::expectException(InvalidQuantityException::class);
        new LineItem('A', 'type', -1);
    }

    /**
     * @throws InvalidQuantityException
     * @throws LineItemNotStackableException
     */
    public function testChangeLineItemToInvalidQuantity()
    {
        self::expectException(InvalidQuantityException::class);
        $lineItem = new LineItem('A', 'type', 1);
        $lineItem->setQuantity(0);
    }

    /**
     * @throws InvalidQuantityException
     * @throws LineItemNotStackableException
     */
    public function testChangeLineItemQuantity()
    {
        $lineItem = new LineItem('A', 'type', 1);
        $lineItem->setStackable(true);
        $lineItem->setQuantity(5);
        self::assertEquals(5, $lineItem->getQuantity());
    }

    /**
     * @throws InvalidQuantityException
     * @throws LineItemNotStackableException
     */
    public function testChangeNonStackableLineItemQuantity()
    {
        self::expectException(LineItemNotStackableException::class);
        $lineItem = new LineItem('A', 'type', 1);
        $lineItem->setStackable(false);
        $lineItem->setQuantity(5);
        self::assertEquals(1, $lineItem->getQuantity());
    }

    /**
     * @throws InvalidChildQuantityException
     * @throws InvalidQuantityException
     * @throws LineItemNotStackableException
     */
    public function testChangeQuantityOfParentLineItem()
    {
        $lineItem = (new LineItem('A', 'type', 1))->setStackable(true);

        $child1 = (new LineItem('A.1', 'child', 3))->setStackable(true);
        $child2 = (new LineItem('A.2', 'child', 2))->setStackable(true);
        $child3 = (new LineItem('A.3', 'child', 1))->setStackable(true);

        $child4 = (new LineItem('A.3.1', 'child', 5))->setStackable(true);
        $child5 = (new LineItem('A.3.2', 'child', 10))->setStackable(true);

        $child3->setChildren(new LineItemCollection([$child4, $child5]));

        $lineItem->setChildren(new LineItemCollection([$child1, $child2, $child3]));

        $lineItem->setQuantity(2);

        self::assertEquals(2, $lineItem->getQuantity());
        self::assertEquals(6, $child1->getQuantity());
        self::assertEquals(4, $child2->getQuantity());
        self::assertEquals(2, $child3->getQuantity());
        self::assertEquals(10, $child4->getQuantity());
        self::assertEquals(20, $child5->getQuantity());

        $lineItem->setQuantity(3);

        self::assertEquals(3, $lineItem->getQuantity());
        self::assertEquals(9, $child1->getQuantity());
        self::assertEquals(6, $child2->getQuantity());
        self::assertEquals(3, $child3->getQuantity());
        self::assertEquals(15, $child4->getQuantity());
        self::assertEquals(30, $child5->getQuantity());

        $lineItem->setQuantity(1);

        self::assertEquals(1, $lineItem->getQuantity());
        self::assertEquals(3, $child1->getQuantity());
        self::assertEquals(2, $child2->getQuantity());
        self::assertEquals(1, $child3->getQuantity());
        self::assertEquals(5, $child4->getQuantity());
        self::assertEquals(10, $child5->getQuantity());
    }

    /**
     * @throws InvalidChildQuantityException
     * @throws InvalidQuantityException
     * @throws LineItemNotStackableException
     */
    public function testChangeQuantityOfParentLineItemWithNonStackableChildren()
    {
        $lineItem = new LineItem('A', 'type', 1);

        $child1 = new LineItem('A.1', 'child', 3);
        $child2 = new LineItem('A.2', 'child', 2);
        $child2->setStackable(false);
        $child3 = new LineItem('A.3', 'child', 1);
        $child3->setStackable(false);

        $child4 = new LineItem('A.3.1', 'child', 5);
        $child5 = new LineItem('A.3.2', 'child', 10);

        $child3->setChildren(new LineItemCollection([$child4, $child5]));

        $lineItem->setChildren(new LineItemCollection([$child1, $child2, $child3]));

        self::expectException(LineItemNotStackableException::class);

        $lineItem->setQuantity(2);

        self::assertEquals(2, $lineItem->getQuantity());
        self::assertEquals(6, $child1->getQuantity());
        self::assertEquals(2, $child2->getQuantity());
        self::assertEquals(1, $child3->getQuantity());
        self::assertEquals(5, $child4->getQuantity());
        self::assertEquals(10, $child5->getQuantity());

        $lineItem->setQuantity(3);

        self::assertEquals(3, $lineItem->getQuantity());
        self::assertEquals(9, $child1->getQuantity());
        self::assertEquals(2, $child2->getQuantity());
        self::assertEquals(1, $child3->getQuantity());
        self::assertEquals(5, $child4->getQuantity());
        self::assertEquals(10, $child5->getQuantity());

        $lineItem->setQuantity(1);

        self::assertEquals(1, $lineItem->getQuantity());
        self::assertEquals(3, $child1->getQuantity());
        self::assertEquals(2, $child2->getQuantity());
        self::assertEquals(1, $child3->getQuantity());
        self::assertEquals(5, $child4->getQuantity());
        self::assertEquals(10, $child5->getQuantity());
    }

    /**
     * @throws InvalidChildQuantityException
     * @throws MixedLineItemTypeException
     * @throws InvalidQuantityException
     */
    public function testAddChildrenToLineItemWithInvalidQuantity()
    {
        $lineItem = new LineItem('A', 'type', 15);

        $child1 = new LineItem('A.1', 'child', 3);
        $child2 = new LineItem('A.2', 'child', 2);
        $child3 = new LineItem('A.3', 'child', 1);

        self::expectException(InvalidChildQuantityException::class);

        $lineItem->addChild($child1);
        $lineItem->addChild($child2);
        $lineItem->addChild($child3);
    }

    /**
     * @throws InvalidChildQuantityException
     * @throws MixedLineItemTypeException
     * @throws InvalidQuantityException
     */
    public function testSetChildrenToLineItemWithInvalidQuantity()
    {
        $lineItem = new LineItem('A', 'type', 15);

        $child1 = new LineItem('A.1', 'child', 3);
        $child2 = new LineItem('A.2', 'child', 2);
        $child3 = new LineItem('A.3', 'child', 1);

        self::expectException(InvalidChildQuantityException::class);

        $lineItem->setChildren(new LineItemCollection([$child1, $child2, $child3]));
    }
}
