<?php declare(strict_types=1);
/**
 * Shopware 5
 * Copyright (c) shopware AG
 *
 * According to our dual licensing model, this program can be used either
 * under the terms of the GNU Affero General Public License, version 3,
 * or under a proprietary license.
 *
 * The texts of the GNU Affero General Public License with an additional
 * permission and of our proprietary license can be found at and
 * in the LICENSE file you have received along with this program.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * "Shopware" is a registered trademark of shopware AG.
 * The licensing of the program under the AGPLv3 does not imply a
 * trademark license. Therefore any rights, title and interest in
 * our trademarks remain entirely with us.
 */

namespace Shopware\Core\Checkout\Test\Cart\Cart;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart\Cart;
use Shopware\Core\Checkout\Cart\Exception\InvalidChildQuantityException;
use Shopware\Core\Checkout\Cart\Exception\InvalidQuantityException;
use Shopware\Core\Checkout\Cart\Exception\LineItemNotFoundException;
use Shopware\Core\Checkout\Cart\Exception\LineItemNotRemoveableException;
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
     * @throws LineItemNotRemoveableException
     */
    public function testRemoveNonRemovableLineItemFromCart()
    {
        $cart = new Cart('test', 'test');

        $lineItem = new LineItem('A', 'test');
        $lineItem->setRemoveable(false);

        $cart->add($lineItem);

        static::expectException(LineItemNotRemoveableException::class);

        $cart->remove($lineItem->getKey());

        static::assertCount(1, $cart->getLineItems());
    }
}
