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

namespace Shopware\Cart\Test\Infrastructure\Validator\Rule;

use PHPUnit\Framework\TestCase;
use Shopware\Cart\Cart\CalculatedCart;
use Shopware\CartBridge\Rule\ShopRule;
use Shopware\Context\Struct\ShopContext;
use Shopware\Framework\Struct\IndexedCollection;
use Shopware\Shop\Struct\ShopDetailStruct;

class ShopRuleTest extends TestCase
{
    public function testEqualsWithSingleShop(): void
    {
        $rule = new ShopRule(['SWAG-SHOP-UUID-1'], ShopRule::OPERATOR_EQ);

        $shop = new ShopDetailStruct();
        $shop->setUuid('SWAG-SHOP-UUID-1');

        $cart = $this->createMock(CalculatedCart::class);

        $context = $this->createMock(ShopContext::class);

        $context->expects($this->any())
            ->method('getShop')
            ->will($this->returnValue($shop));

        $this->assertTrue($rule->match($cart, $context, new IndexedCollection())->matches());
    }

    public function testEqualsWithMultipleShops(): void
    {
        $rule = new ShopRule(['SWAG-SHOP-UUID-2', 'SWAG-SHOP-UUID-3', 'SWAG-SHOP-UUID-4', 'SWAG-SHOP-UUID-1'], ShopRule::OPERATOR_EQ);

        $shop = new ShopDetailStruct();
        $shop->setUuid('SWAG-SHOP-UUID-3');

        $cart = $this->createMock(CalculatedCart::class);

        $context = $this->createMock(ShopContext::class);

        $context->expects($this->any())
            ->method('getShop')
            ->will($this->returnValue($shop));

        $this->assertTrue(
            $rule->match($cart, $context, new IndexedCollection())->matches()
        );
    }

    public function testEqualsNotMatchWithSingleShop(): void
    {
        $rule = new ShopRule(['SWAG-SHOP-UUID-11'], ShopRule::OPERATOR_EQ);

        $shop = new ShopDetailStruct();
        $shop->setUuid('SWAG-SHOP-UUID-1');

        $cart = $this->createMock(CalculatedCart::class);

        $context = $this->createMock(ShopContext::class);

        $context->expects($this->any())
            ->method('getShop')
            ->will($this->returnValue($shop));

        $this->assertFalse(
            $rule->match($cart, $context, new IndexedCollection())->matches()
        );
    }

    public function testEqualsNotMatchWithMultipleShops(): void
    {
        $rule = new ShopRule(['SWAG-SHOP-UUID-2', 'SWAG-SHOP-UUID-3', 'SWAG-SHOP-UUID-4', 'SWAG-SHOP-UUID-1'], ShopRule::OPERATOR_EQ);

        $shop = new ShopDetailStruct();
        $shop->setUuid('SWAG-SHOP-UUID-11');

        $cart = $this->createMock(CalculatedCart::class);

        $context = $this->createMock(ShopContext::class);

        $context->expects($this->any())
            ->method('getShop')
            ->will($this->returnValue($shop));

        $this->assertFalse(
            $rule->match($cart, $context, new IndexedCollection())->matches()
        );
    }

    public function testNotEqualsWithSingleShop(): void
    {
        $rule = new ShopRule(['SWAG-SHOP-UUID-1'], ShopRule::OPERATOR_NEQ);

        $shop = new ShopDetailStruct();
        $shop->setUuid('SWAG-SHOP-UUID-1');

        $cart = $this->createMock(CalculatedCart::class);

        $context = $this->createMock(ShopContext::class);

        $context->expects($this->any())
            ->method('getShop')
            ->will($this->returnValue($shop));

        $this->assertFalse($rule->match($cart, $context, new IndexedCollection())->matches());
    }

    public function testNotEqualsWithMultipleShops(): void
    {
        $rule = new ShopRule(['SWAG-SHOP-UUID-2', 'SWAG-SHOP-UUID-3', 'SWAG-SHOP-UUID-4', 'SWAG-SHOP-UUID-1'], ShopRule::OPERATOR_NEQ);

        $shop = new ShopDetailStruct();
        $shop->setUuid('SWAG-SHOP-UUID-3');

        $cart = $this->createMock(CalculatedCart::class);

        $context = $this->createMock(ShopContext::class);

        $context->expects($this->any())
            ->method('getShop')
            ->will($this->returnValue($shop));

        $this->assertFalse($rule->match($cart, $context, new IndexedCollection())->matches());
    }

    public function testNotEqualsNotMatchWithSingleShop(): void
    {
        $rule = new ShopRule(['SWAG-SHOP-UUID-11'], ShopRule::OPERATOR_NEQ);

        $shop = new ShopDetailStruct();
        $shop->setUuid('SWAG-SHOP-UUID-1');

        $cart = $this->createMock(CalculatedCart::class);

        $context = $this->createMock(ShopContext::class);

        $context->expects($this->any())
            ->method('getShop')
            ->will($this->returnValue($shop));

        $this->assertTrue($rule->match($cart, $context, new IndexedCollection())->matches());
    }

    public function testNotEqualsNotMatchWithMultipleShops(): void
    {
        $rule = new ShopRule(['SWAG-SHOP-UUID-2', 'SWAG-SHOP-UUID-3', 'SWAG-SHOP-UUID-4', 'SWAG-SHOP-UUID-1'], ShopRule::OPERATOR_NEQ);

        $shop = new ShopDetailStruct();
        $shop->setUuid('SWAG-SHOP-UUID-11');

        $cart = $this->createMock(CalculatedCart::class);

        $context = $this->createMock(ShopContext::class);

        $context->expects($this->any())
            ->method('getShop')
            ->will($this->returnValue($shop));

        $this->assertTrue($rule->match($cart, $context, new IndexedCollection())->matches());
    }

    /**
     * @dataProvider unsupportedOperators
     *
     * @expectedException \Shopware\Cart\Rule\Exception\UnsupportedOperatorException
     *
     * @param string $operator
     */
    public function testUnsupportedOperators(string $operator): void
    {
        $rule = new ShopRule(['SWAG-SHOP-UUID-1'], $operator);
        $shop = new ShopDetailStruct();
        $shop->setUuid('SWAG-SHOP-UUID-1');

        $cart = $this->createMock(CalculatedCart::class);

        $context = $this->createMock(ShopContext::class);

        $context->expects($this->any())
            ->method('getShop')
            ->will($this->returnValue($shop));

        $rule->match($cart, $context, new IndexedCollection());
    }

    public function unsupportedOperators(): array
    {
        return [
            [true],
            [false],
            [''],
            [\Shopware\Cart\Rule\Rule::OPERATOR_GTE],
            [\Shopware\Cart\Rule\Rule::OPERATOR_LTE],
        ];
    }
}
