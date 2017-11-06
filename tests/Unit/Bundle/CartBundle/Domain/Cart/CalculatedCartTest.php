<?php
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

namespace Shopware\Tests\Unit\Bundle\CartBundle\Domain\Cart;

use PHPUnit\Framework\TestCase;
use Shopware\Cart\Cart\Struct\CalculatedCart;
use Shopware\Cart\Cart\Struct\CartContainer;
use Shopware\Cart\Delivery\Struct\DeliveryCollection;
use Shopware\Cart\LineItem\CalculatedLineItemCollection;
use Shopware\Cart\Price\Struct\CartPrice;
use Shopware\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Tests\Unit\Bundle\CartBundle\Common\ConfiguredGoodsItem;
use Shopware\Tests\Unit\Bundle\CartBundle\Common\ConfiguredLineItem;

class CalculatedCartTest extends TestCase
{
    public function testEmptyCartHasNoGoods(): void
    {
        $cart = new \Shopware\Cart\Cart\Struct\CalculatedCart(
            \Shopware\Cart\Cart\Struct\CartContainer::createNew('test'),
            new CalculatedLineItemCollection(),
            new CartPrice(0, 0, 0, new CalculatedTaxCollection(), new TaxRuleCollection(), CartPrice::TAX_STATE_GROSS),
            new DeliveryCollection()
        );

        static::assertCount(0, $cart->getCalculatedLineItems()->filterGoods());
    }

    public function testCartWithLineItemsHasGoods(): void
    {
        $cart = new \Shopware\Cart\Cart\Struct\CalculatedCart(
            \Shopware\Cart\Cart\Struct\CartContainer::createNew('test'),
            new CalculatedLineItemCollection([
                new ConfiguredGoodsItem('A', 1),
                new ConfiguredLineItem('B', 1),
            ]),
            new CartPrice(0, 0, 0, new CalculatedTaxCollection(), new TaxRuleCollection(), CartPrice::TAX_STATE_GROSS),
            new DeliveryCollection()
        );

        static::assertCount(1, $cart->getCalculatedLineItems()->filterGoods());
    }

    public function testCartHasNoGoodsIfNoLineItemDefinedAsGoods(): void
    {
        $cart = new \Shopware\Cart\Cart\Struct\CalculatedCart(
            \Shopware\Cart\Cart\Struct\CartContainer::createNew('test'),
            new CalculatedLineItemCollection([
                new ConfiguredLineItem('A', 1),
                new ConfiguredLineItem('B', 1),
            ]),
            new CartPrice(0, 0, 0, new CalculatedTaxCollection(), new TaxRuleCollection(), CartPrice::TAX_STATE_GROSS),
            new DeliveryCollection()
        );

        static::assertCount(0, $cart->getCalculatedLineItems()->filterGoods());
    }
}
