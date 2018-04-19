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

namespace Shopware\Tests\Unit\Bundle\CartBundle\Infrastructure\Validator\Rule;

use PHPUnit\Framework\TestCase;
use Shopware\Cart\Cart\CalculatedCart;
use Shopware\CartBridge\Rule\Data\OrderClearedStateRuleData;
use Shopware\CartBridge\Rule\OrderClearedStateRule;
use Shopware\Framework\Struct\StructCollection;
use Shopware\Context\Struct\ShopContext;

class OrderClearedStateRuleTest extends TestCase
{
    public function testRuleWithState(): void
    {
        $rule = new OrderClearedStateRule([1]);

        $cart = $this->createMock(CalculatedCart::class);

        $context = $this->createMock(ShopContext::class);

        $this->assertTrue(
            $rule->match($cart, $context, new StructCollection([
                OrderClearedStateRuleData::class => new OrderClearedStateRuleData([1]),
            ]))->matches()
        );
    }

    public function testRuleWithStates(): void
    {
        $rule = new OrderClearedStateRule([1, 2, 3]);

        $cart = $this->createMock(CalculatedCart::class);

        $context = $this->createMock(ShopContext::class);

        $this->assertTrue(
            $rule->match($cart, $context, new StructCollection([
                OrderClearedStateRuleData::class => new OrderClearedStateRuleData([1]),
            ]))->matches()
        );
    }

    public function testRuleNotMatch(): void
    {
        $rule = new OrderClearedStateRule([1, 2, 3]);

        $cart = $this->createMock(CalculatedCart::class);

        $context = $this->createMock(ShopContext::class);

        $this->assertFalse(
            $rule->match($cart, $context, new StructCollection([
                OrderClearedStateRuleData::class => new OrderClearedStateRuleData([5]),
            ]))->matches()
        );
    }

    public function testRuleWithoutDataObject(): void
    {
        $rule = new OrderClearedStateRule([1, 2, 3]);

        $cart = $this->createMock(CalculatedCart::class);

        $context = $this->createMock(ShopContext::class);

        $this->assertFalse(
            $rule->match($cart, $context, new StructCollection())->matches()
        );
    }
}
