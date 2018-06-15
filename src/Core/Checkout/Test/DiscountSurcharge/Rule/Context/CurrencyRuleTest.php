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

namespace Shopware\Core\Checkout\Test\DiscountSurcharge\Rule\Context;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart\Struct\CalculatedCart;
use Shopware\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Rule\CurrencyRule;

class CurrencyRuleTest extends TestCase
{
    public function testMatch(): void
    {
        $rule = new CurrencyRule(['SWAG-CURRENCY-ID-1']);

        $cart = $this->createMock(CalculatedCart::class);

        $checkoutContext = $this->createMock(CheckoutContext::class);

        $context = $this->createMock(Context::class);
        $context->expects($this->any())
            ->method('getCurrencyId')
            ->will($this->returnValue('SWAG-CURRENCY-ID-1'));

        $checkoutContext->expects($this->any())
            ->method('getContext')
            ->will($this->returnValue($context));

        $this->assertTrue(
            $rule->match(new CartRuleScope($cart, $checkoutContext))->matches()
        );
    }

    public function testNotMatch(): void
    {
        $rule = new \Shopware\Core\Framework\Rule\CurrencyRule(['SWAG-CURRENCY-ID-2', 'SWAG-CURRENCY-ID-3', 'SWAG-CURRENCY-ID-1']);

        $cart = $this->createMock(CalculatedCart::class);

        $checkoutContext = $this->createMock(CheckoutContext::class);

        $context = $this->createMock(Context::class);
        $context->expects($this->any())
            ->method('getCurrencyId')
            ->will($this->returnValue('SWAG-CURRENCY-ID-5'));

        $checkoutContext->expects($this->any())
            ->method('getContext')
            ->will($this->returnValue($context));

        $this->assertFalse(
            $rule->match(new CartRuleScope($cart, $checkoutContext))->matches()
        );
    }

    public function testMultipleCurrencies(): void
    {
        $rule = new CurrencyRule(['SWAG-CURRENCY-ID-2', 'SWAG-CURRENCY-ID-3', 'SWAG-CURRENCY-ID-1']);

        $cart = $this->createMock(CalculatedCart::class);

        $checkoutContext = $this->createMock(CheckoutContext::class);

        $context = $this->createMock(Context::class);
        $context->expects($this->any())
            ->method('getCurrencyId')
            ->will($this->returnValue('SWAG-CURRENCY-ID-3'));

        $checkoutContext->expects($this->any())
            ->method('getContext')
            ->will($this->returnValue($context));

        $this->assertTrue(
            $rule->match(new CartRuleScope($cart, $checkoutContext))->matches()
        );
    }
}
