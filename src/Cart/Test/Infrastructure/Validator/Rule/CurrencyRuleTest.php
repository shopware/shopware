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

namespace Shopware\Cart\Test\Infrastructure\Validator\Rule;

use PHPUnit\Framework\TestCase;
use Shopware\Cart\Cart\CalculatedCart;
use Shopware\CartBridge\Rule\CurrencyRule;
use Shopware\Context\Struct\ShopContext;
use Shopware\Currency\Struct\CurrencyBasicStruct;
use Shopware\Framework\Struct\StructCollection;

class CurrencyRuleTest extends TestCase
{
    public function testMatch(): void
    {
        $rule = new CurrencyRule(['SWAG-CURRENCY-UUID-1']);

        $cart = $this->createMock(CalculatedCart::class);

        $currency = new CurrencyBasicStruct();
        $currency->setUuid('SWAG-CURRENCY-UUID-1');

        $context = $this->createMock(ShopContext::class);

        $context->expects($this->any())
            ->method('getCurrency')
            ->will($this->returnValue($currency));

        $this->assertTrue(
            $rule->match($cart, $context, new StructCollection())->matches()
        );
    }

    public function testNotMatch(): void
    {
        $rule = new CurrencyRule(['SWAG-CURRENCY-UUID-2', 'SWAG-CURRENCY-UUID-3', 'SWAG-CURRENCY-UUID-1']);

        $cart = $this->createMock(CalculatedCart::class);

        $currency = new CurrencyBasicStruct();
        $currency->setUuid('SWAG-CURRENCY-UUID-5');

        $context = $this->createMock(ShopContext::class);

        $context->expects($this->any())
            ->method('getCurrency')
            ->will($this->returnValue($currency));

        $this->assertFalse(
            $rule->match($cart, $context, new StructCollection())->matches()
        );
    }

    public function testMultipleCurrencies(): void
    {
        $rule = new CurrencyRule(['SWAG-CURRENCY-UUID-2', 'SWAG-CURRENCY-UUID-3', 'SWAG-CURRENCY-UUID-1']);

        $cart = $this->createMock(CalculatedCart::class);

        $currency = new CurrencyBasicStruct();
        $currency->setUuid('SWAG-CURRENCY-UUID-3');

        $context = $this->createMock(ShopContext::class);

        $context->expects($this->any())
            ->method('getCurrency')
            ->will($this->returnValue($currency));

        $this->assertTrue(
            $rule->match($cart, $context, new StructCollection())->matches()
        );
    }
}
