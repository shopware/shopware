<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Exception\InvalidChildQuantityException;
use Shopware\Core\Checkout\Cart\Exception\InvalidQuantityException;
use Shopware\Core\Checkout\Cart\Exception\LineItemNotFoundException;
use Shopware\Core\Checkout\Cart\Exception\LineItemNotRemovableException;
use Shopware\Core\Checkout\Cart\Exception\MixedLineItemTypeException;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;

class CartTest extends TestCase
{
    public function testEmptyCartHasNoGoods(): void
    {
        $cart = new Cart('test', 'test');
        static::assertCount(0, $cart->getLineItems()->filterGoods());
    }

    public function testCartWithLineItemsHasGoods(): void
    {
        $cart = new Cart('test', 'test');
        $cart->add(
            (new LineItem('A', 'test'))
                ->setGood(true)
                ->setStackable(true)
        );
        $cart->add(
            (new LineItem('A', 'test'))
                ->setGood(false)
                ->setStackable(true)
        );

        static::assertCount(1, $cart->getLineItems()->filterGoods());
    }

    public function testCartHasNoGoodsIfNoLineItemDefinedAsGoods(): void
    {
        $cart = new Cart('test', 'test');

        $cart->add((new LineItem('A', 'test'))->setGood(false));
        $cart->add((new LineItem('B', 'test'))->setGood(false));

        static::assertCount(0, $cart->getLineItems()->filterGoods());
    }

    public function testCartWithNestedLineItemHasChildren(): void
    {
        $cart = new Cart('test', 'test');

        $cart->add(
            (new LineItem('nested', 'nested'))
                ->setChildren(
                    new LineItemCollection([
                        (new LineItem('A', 'test'))->setGood(true),
                        (new LineItem('B', 'test'))->setGood(true),
                    ])
                )
        );

        $cart->add(
            (new LineItem('flat', 'flat'))->setGood(true)
        );

        static::assertCount(4, $cart->getLineItems()->getFlat());
        static::assertCount(2, $cart->getLineItems());
    }

    /**
     * @throws InvalidChildQuantityException
     * @throws InvalidQuantityException
     * @throws MixedLineItemTypeException
     * @throws LineItemNotFoundException
     * @throws LineItemNotRemovableException
     */
    public function testRemoveNonRemovableLineItemFromCart(): void
    {
        $cart = new Cart('test', 'test');

        $lineItem = new LineItem('A', 'test');
        $lineItem->setRemovable(false);

        $cart->add($lineItem);

        $this->expectException(LineItemNotRemovableException::class);

        $cart->remove($lineItem->getId());

        static::assertCount(1, $cart->getLineItems());
    }
}
