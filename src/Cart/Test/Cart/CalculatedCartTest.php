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

namespace Shopware\Cart\Test\Cart;

use PHPUnit\Framework\TestCase;
use Shopware\Cart\Cart\Struct\CalculatedCart;
use Shopware\Cart\Cart\Struct\CartContainer;
use Shopware\Cart\Delivery\Struct\DeliveryCollection;
use Shopware\Cart\Error\ErrorCollection;
use Shopware\Cart\LineItem\CalculatedLineItem;
use Shopware\Cart\LineItem\CalculatedLineItemCollection;
use Shopware\Cart\LineItem\CalculatedLineItemInterface;
use Shopware\Cart\LineItem\GoodsInterface;
use Shopware\Cart\LineItem\LineItemCollection;
use Shopware\Cart\LineItem\LineItemInterface;
use Shopware\Cart\LineItem\NestedInterface;
use Shopware\Cart\Price\Struct\CartPrice;
use Shopware\Cart\Price\Struct\Price;
use Shopware\Cart\Rule\Rule;
use Shopware\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Cart\Tax\Struct\TaxRuleCollection;

class CalculatedCartTest extends TestCase
{
    public function testEmptyCartHasNoGoods(): void
    {
        $cart = new CalculatedCart(
            new CartContainer('test', 'test', new LineItemCollection(), new ErrorCollection()),
            new CalculatedLineItemCollection(),
            new CartPrice(0, 0, 0, new CalculatedTaxCollection(), new TaxRuleCollection(), CartPrice::TAX_STATE_GROSS),
            new DeliveryCollection()
        );

        static::assertCount(0, $cart->getCalculatedLineItems()->filterGoods());
    }

    public function testCartWithLineItemsHasGoods(): void
    {
        $cart = new CalculatedCart(
            new CartContainer('test', 'test', new LineItemCollection(), new ErrorCollection()),
            new CalculatedLineItemCollection([
                new ConfiguredGoods('A', new Price(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection()), 1, 'A'),
                new CalculatedLineItem('B', new Price(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection()), 1, 'B'),
            ]),
            new CartPrice(0, 0, 0, new CalculatedTaxCollection(), new TaxRuleCollection(), CartPrice::TAX_STATE_GROSS),
            new DeliveryCollection()
        );

        static::assertCount(1, $cart->getCalculatedLineItems()->filterGoods());
    }

    public function testCartHasNoGoodsIfNoLineItemDefinedAsGoods(): void
    {
        $cart = new CalculatedCart(
            new CartContainer('test', 'test', new LineItemCollection(), new ErrorCollection()),
            new CalculatedLineItemCollection([
                new CalculatedLineItem('A', new Price(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection()), 1, 'A'),
                new CalculatedLineItem('B', new Price(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection()), 1, 'B'),
            ]),
            new CartPrice(0, 0, 0, new CalculatedTaxCollection(), new TaxRuleCollection(), CartPrice::TAX_STATE_GROSS),
            new DeliveryCollection()
        );

        static::assertCount(0, $cart->getCalculatedLineItems()->filterGoods());
    }

    public function testCartWithNestedLineItemHasChildren(): void
    {
        $price = new Price(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection());

        $lineItem = new NestedLineItem('X', $price, 1, 'nested',
            new CalculatedLineItemCollection([
                new CalculatedLineItem('B', new Price(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection()), 1, 'B'),
                new CalculatedLineItem('C', new Price(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection()), 1, 'C'),
            ])
        );

        $cart = new CalculatedCart(
            new CartContainer('test', 'test', new LineItemCollection(), new ErrorCollection()),
            new CalculatedLineItemCollection([
                new CalculatedLineItem('A', new Price(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection()), 1, 'A'),
                $lineItem,
            ]),
            new CartPrice(0, 0, 0, new CalculatedTaxCollection(), new TaxRuleCollection(), CartPrice::TAX_STATE_GROSS),
            new DeliveryCollection()
        );

        $this->assertCount(4, $cart->getCalculatedLineItems()->getFlatElements());
        $this->assertCount(2, $cart->getCalculatedLineItems());
    }
}

class NestedLineItem extends CalculatedLineItem implements NestedInterface
{
    /**
     * @var CalculatedLineItemCollection
     */
    private $children;

    public function __construct(
        string $identifier,
        Price $price,
        int $quantity,
        string $type,
        CalculatedLineItemCollection $children,
        ?LineItemInterface $lineItem = null,
        ?Rule $rule = null
    ) {
        parent::__construct($identifier, $price, $quantity, $type, $lineItem, $rule);
        $this->children = $children;
    }

    public function getChildren(): CalculatedLineItemCollection
    {
        return $this->children;
    }
}

class ConfiguredGoods extends CalculatedLineItem implements GoodsInterface
{
}
