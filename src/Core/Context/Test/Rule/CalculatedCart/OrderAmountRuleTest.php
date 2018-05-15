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

namespace Shopware\Context\Test\Rule\CalculatedCart;

use PHPUnit\Framework\TestCase;
use Shopware\Checkout\Cart\Test\Common\Generator;
use Shopware\Context\MatchContext\CartRuleMatchContext;
use Shopware\Context\Rule\CalculatedCart\OrderAmountRule;
use Shopware\Context\Struct\StorefrontContext;

class OrderAmountRuleTest extends TestCase
{
    public function testRuleWithExactAmountMatch(): void
    {
        $rule = new OrderAmountRule(275, OrderAmountRule::OPERATOR_EQ);

        $calculatedCart = Generator::createCalculatedCart();
        $context = $this->createMock(StorefrontContext::class);

        $this->assertTrue(
            $rule->match(new CartRuleMatchContext($calculatedCart, $context))->matches()
        );
    }

    public function testRuleWithExactAmountNotMatch(): void
    {
        $rule = new OrderAmountRule(0, OrderAmountRule::OPERATOR_EQ);

        $calculatedCart = Generator::createCalculatedCart();
        $context = $this->createMock(StorefrontContext::class);

        $this->assertFalse(
            $rule->match(new CartRuleMatchContext($calculatedCart, $context))->matches()
        );
    }

    public function testRuleWithLowerThanEqualExactAmountMatch(): void
    {
        $rule = new OrderAmountRule(275, OrderAmountRule::OPERATOR_LTE);

        $calculatedCart = Generator::createCalculatedCart();
        $context = $this->createMock(StorefrontContext::class);

        $this->assertTrue(
            $rule->match(new CartRuleMatchContext($calculatedCart, $context))->matches()
        );
    }

    public function testRuleWithLowerThanEqualAmountMatch(): void
    {
        $rule = new OrderAmountRule(300, OrderAmountRule::OPERATOR_LTE);

        $calculatedCart = Generator::createCalculatedCart();
        $context = $this->createMock(StorefrontContext::class);

        $this->assertTrue(
            $rule->match(new CartRuleMatchContext($calculatedCart, $context))->matches()
        );
    }

    public function testRuleWithLowerThanEqualAmountNotMatch(): void
    {
        $rule = new OrderAmountRule(274, OrderAmountRule::OPERATOR_LTE);

        $calculatedCart = Generator::createCalculatedCart();
        $context = $this->createMock(StorefrontContext::class);

        $this->assertFalse(
            $rule->match(new CartRuleMatchContext($calculatedCart, $context))->matches()
        );
    }

    public function testRuleWithGreaterThanEqualExactAmountMatch(): void
    {
        $rule = new OrderAmountRule(275, OrderAmountRule::OPERATOR_GTE);

        $calculatedCart = Generator::createCalculatedCart();
        $context = $this->createMock(StorefrontContext::class);

        $this->assertTrue(
            $rule->match(new CartRuleMatchContext($calculatedCart, $context))->matches()
        );
    }

    public function testRuleWithGreaterThanEqualAmountMatch(): void
    {
        $rule = new OrderAmountRule(100, OrderAmountRule::OPERATOR_GTE);

        $calculatedCart = Generator::createCalculatedCart();
        $context = $this->createMock(StorefrontContext::class);

        $this->assertTrue(
            $rule->match(new CartRuleMatchContext($calculatedCart, $context))->matches()
        );
    }

    public function testRuleWithGreaterThanEqualAmountNotMatch(): void
    {
        $rule = new OrderAmountRule(276, OrderAmountRule::OPERATOR_GTE);

        $calculatedCart = Generator::createCalculatedCart();
        $context = $this->createMock(StorefrontContext::class);

        $this->assertFalse(
            $rule->match(new CartRuleMatchContext($calculatedCart, $context))->matches()
        );
    }

    public function testRuleNotEqualAmountMatch(): void
    {
        $rule = new OrderAmountRule(0, OrderAmountRule::OPERATOR_NEQ);

        $calculatedCart = Generator::createCalculatedCart();
        $context = $this->createMock(StorefrontContext::class);

        $this->assertTrue(
            $rule->match(new CartRuleMatchContext($calculatedCart, $context))->matches()
        );
    }

    public function testRuleNotEqualAmountNotMatch(): void
    {
        $rule = new OrderAmountRule(275, OrderAmountRule::OPERATOR_NEQ);

        $calculatedCart = Generator::createCalculatedCart();
        $context = $this->createMock(StorefrontContext::class);

        $this->assertFalse(
            $rule->match(new CartRuleMatchContext($calculatedCart, $context))->matches()
        );
    }

    /**
     * @dataProvider unsupportedOperators
     *
     * @expectedException \Shopware\Context\Exception\UnsupportedOperatorException
     *
     * @param string $operator
     */
    public function testUnsupportedOperators(string $operator): void
    {
        $rule = new OrderAmountRule(100, $operator);

        $calculatedCart = Generator::createCalculatedCart();
        $context = $this->createMock(StorefrontContext::class);

        $this->assertFalse(
            $rule->match(new CartRuleMatchContext($calculatedCart, $context))->matches()
        );
    }

    public function unsupportedOperators(): array
    {
        return [
            [true],
            [false],
            [''],
        ];
    }
}
