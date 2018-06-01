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

namespace Shopware\Application\Test\Context\Rule\CalculatedCart;

use PHPUnit\Framework\TestCase;
use Shopware\Checkout\CustomerContext;
use Shopware\Checkout\Rule\Specification\CalculatedCart\LineItemsInCartRule;
use Shopware\Checkout\Rule\Specification\Scope\CartRuleScope;
use Shopware\Checkout\Test\Cart\Common\Generator;

class LineItemsInCartRuleTest extends TestCase
{
    public function testRuleWithExactLineItemsMatch(): void
    {
        $rule = new LineItemsInCartRule(['A', 'B']);

        $calculatedCart = Generator::createCalculatedCart();
        $context = $this->createMock(CustomerContext::class);

        $this->assertTrue(
            $rule->match(new CartRuleScope($calculatedCart, $context))->matches()
        );
    }

    public function testRuleWithLineItemsNotMatch(): void
    {
        $rule = new LineItemsInCartRule(['C', 'D']);

        $calculatedCart = Generator::createCalculatedCart();
        $context = $this->createMock(CustomerContext::class);

        $this->assertFalse(
            $rule->match(new CartRuleScope($calculatedCart, $context))->matches()
        );
    }

    public function testRuleWithLineItemSubsetMatch(): void
    {
        $rule = new LineItemsInCartRule(['B']);

        $calculatedCart = Generator::createCalculatedCart();
        $context = $this->createMock(CustomerContext::class);

        $this->assertTrue(
            $rule->match(new CartRuleScope($calculatedCart, $context))->matches()
        );
    }
}
