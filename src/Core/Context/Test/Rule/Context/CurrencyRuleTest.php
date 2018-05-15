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

namespace Shopware\Context\Test\Rule\Context;

use PHPUnit\Framework\TestCase;
use Shopware\System\Currency\Struct\CurrencyBasicStruct;
use Shopware\Checkout\Cart\Cart\Struct\CalculatedCart;
use Shopware\Context\MatchContext\CartRuleMatchContext;
use Shopware\Context\Rule\Context\CurrencyRule;
use Shopware\Context\Struct\StorefrontContext;

class CurrencyRuleTest extends TestCase
{
    public function testMatch(): void
    {
        $rule = new CurrencyRule(['SWAG-CURRENCY-ID-1']);

        $cart = $this->createMock(CalculatedCart::class);

        $currency = new CurrencyBasicStruct();
        $currency->setId('SWAG-CURRENCY-ID-1');

        $context = $this->createMock(StorefrontContext::class);

        $context->expects($this->any())
            ->method('getCurrency')
            ->will($this->returnValue($currency));

        $this->assertTrue(
            $rule->match(new CartRuleMatchContext($cart, $context))->matches()
        );
    }

    public function testNotMatch(): void
    {
        $rule = new CurrencyRule(['SWAG-CURRENCY-ID-2', 'SWAG-CURRENCY-ID-3', 'SWAG-CURRENCY-ID-1']);

        $cart = $this->createMock(CalculatedCart::class);

        $currency = new CurrencyBasicStruct();
        $currency->setId('SWAG-CURRENCY-ID-5');

        $context = $this->createMock(StorefrontContext::class);

        $context->expects($this->any())
            ->method('getCurrency')
            ->will($this->returnValue($currency));

        $this->assertFalse(
            $rule->match(new CartRuleMatchContext($cart, $context))->matches()
        );
    }

    public function testMultipleCurrencies(): void
    {
        $rule = new CurrencyRule(['SWAG-CURRENCY-ID-2', 'SWAG-CURRENCY-ID-3', 'SWAG-CURRENCY-ID-1']);

        $cart = $this->createMock(CalculatedCart::class);

        $currency = new CurrencyBasicStruct();
        $currency->setId('SWAG-CURRENCY-ID-3');

        $context = $this->createMock(StorefrontContext::class);

        $context->expects($this->any())
            ->method('getCurrency')
            ->will($this->returnValue($currency));

        $this->assertTrue(
            $rule->match(new CartRuleMatchContext($cart, $context))->matches()
        );
    }
}
