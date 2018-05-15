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

namespace Shopware\Checkout\Cart\Test\Cart;

use PHPUnit\Framework\TestCase;
use Shopware\Checkout\Cart\Cart\CartProcessor;
use Shopware\Checkout\Cart\Cart\Struct\CalculatedCart;
use Shopware\Checkout\Cart\Cart\Struct\Cart;
use Shopware\Checkout\Cart\Delivery\Struct\DeliveryCollection;
use Shopware\Checkout\Cart\LineItem\CalculatedLineItemCollection;
use Shopware\Checkout\Cart\Price\AmountCalculator;
use Shopware\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\CartBridge\Product\ProductProcessor;
use Shopware\Context\Struct\StorefrontContext;
use Shopware\Framework\Struct\StructCollection;

class CartCalculatorTest extends TestCase
{
    public function testIterateAllProcessors(): void
    {
        $price = new CartPrice(0, 0, 0, new CalculatedTaxCollection(), new TaxRuleCollection(), CartPrice::TAX_STATE_GROSS);

        $generator = $this->createMock(AmountCalculator::class);
        $generator->expects($this->exactly(2))
            ->method('calculateAmount')
            ->will($this->returnValue($price));

        $productProcessor = $this->createMock(ProductProcessor::class);
        $productProcessor->expects($this->once())->method('process');

        $calculator = new CartProcessor(
            [$productProcessor],
            $generator
        );

        $container = Cart::createNew('test');

        $cart = new CalculatedCart(
            $container,
            new CalculatedLineItemCollection(),
            $price,
            new DeliveryCollection()
        );

        $this->assertEquals(
            $cart,
            $calculator->process($container, $this->createMock(StorefrontContext::class), new StructCollection())
        );
    }
}
