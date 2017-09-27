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
use Shopware\CartBridge\Rule\Data\LastOrderRuleData;
use Shopware\CartBridge\Rule\LastOrderRule;
use Shopware\Context\Struct\ShopContext;
use Shopware\Framework\Struct\StructCollection;

class LastOrderRuleTest extends TestCase
{
    public function testRuleWithExactDate(): void
    {
        $rule = new LastOrderRule(10);

        $cart = $this->createMock(CalculatedCart::class);

        $context = $this->createMock(ShopContext::class);

        $date = (new \DateTime())->sub(
            new \DateInterval('P' . (int) 10 . 'D')
        );

        $this->assertTrue(
            $rule->match($cart, $context, new StructCollection([
                LastOrderRuleData::class => new LastOrderRuleData($date),
            ]))->matches()
        );
    }

    public function testRuleNotMatch(): void
    {
        $rule = new LastOrderRule(10);

        $cart = $this->createMock(CalculatedCart::class);

        $context = $this->createMock(ShopContext::class);

        $date = (new \DateTime())->sub(
            new \DateInterval('P' . (int) 9 . 'D')
        );

        $this->assertFalse(
            $rule->match($cart, $context, new StructCollection([
                LastOrderRuleData::class => new LastOrderRuleData($date),
            ]))->matches()
        );
    }

    public function testRuleWithDateBefore(): void
    {
        $rule = new LastOrderRule(10);

        $cart = $this->createMock(CalculatedCart::class);

        $context = $this->createMock(ShopContext::class);

        $date = (new \DateTime())->sub(
            new \DateInterval('P' . (int) 50 . 'D')
        );

        $this->assertTrue(
            $rule->match($cart, $context, new StructCollection([
                LastOrderRuleData::class => new LastOrderRuleData($date),
            ]))->matches()
        );
    }

    public function testWithoutDataObject(): void
    {
        $rule = new LastOrderRule(10);

        $cart = $this->createMock(CalculatedCart::class);

        $context = $this->createMock(ShopContext::class);

        $this->assertFalse(
            $rule->match($cart, $context, new StructCollection())->matches()
        );
    }
}
