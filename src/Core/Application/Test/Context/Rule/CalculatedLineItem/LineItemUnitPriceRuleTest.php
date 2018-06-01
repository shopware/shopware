<?php declare(strict_types=1);
/**
 * Shopware\Core 5
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
 * "Shopware\Core" is a registered trademark of shopware AG.
 * The licensing of the program under the AGPLv3 does not imply a
 * trademark license. Therefore any rights, title and interest in
 * our trademarks remain entirely with us.
 */

namespace Shopware\Core\Application\Test\Context\Rule\CalculatedLineItem;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\CustomerContext;
use Shopware\Core\Checkout\Rule\Specification\CalculatedLineItem\LineItemUnitPriceRule;
use Shopware\Core\Checkout\Rule\Specification\Rule;
use Shopware\Core\Checkout\Rule\Specification\Scope\CalculatedLineItemScope;
use Shopware\Core\Checkout\Test\Cart\Common\Generator;

class LineItemUnitPriceRuleTest extends TestCase
{
    public function testRuleWithExactAmountMatch(): void
    {
        $rule = new LineItemUnitPriceRule(100);

        $calculatedLineItem = Generator::createCalculatedProduct('A', 100, 2);
        $context = $this->createMock(CustomerContext::class);

        $this->assertTrue(
            $rule->match(new CalculatedLineItemScope($calculatedLineItem, $context))->matches()
        );
    }

    public function testRuleWithExactAmountNotMatch(): void
    {
        $rule = new LineItemUnitPriceRule(99);

        $calculatedLineItem = Generator::createCalculatedProduct('A', 100, 2);
        $context = $this->createMock(CustomerContext::class);

        $this->assertFalse(
            $rule->match(new CalculatedLineItemScope($calculatedLineItem, $context))->matches()
        );
    }

    public function testRuleWithLowerThanEqualExactAmountMatch(): void
    {
        $rule = new LineItemUnitPriceRule(100, Rule::OPERATOR_LTE);

        $calculatedLineItem = Generator::createCalculatedProduct('A', 100, 2);
        $context = $this->createMock(CustomerContext::class);

        $this->assertTrue(
            $rule->match(new CalculatedLineItemScope($calculatedLineItem, $context))->matches()
        );
    }

    public function testRuleWithLowerThanEqualAmountMatch(): void
    {
        $rule = new LineItemUnitPriceRule(101, Rule::OPERATOR_LTE);

        $calculatedLineItem = Generator::createCalculatedProduct('A', 100, 2);
        $context = $this->createMock(CustomerContext::class);

        $this->assertTrue(
            $rule->match(new CalculatedLineItemScope($calculatedLineItem, $context))->matches()
        );
    }

    public function testRuleWithLowerThanEqualAmountNotMatch(): void
    {
        $rule = new LineItemUnitPriceRule(99, Rule::OPERATOR_LTE);

        $calculatedLineItem = Generator::createCalculatedProduct('A', 100, 2);
        $context = $this->createMock(CustomerContext::class);

        $this->assertFalse(
            $rule->match(new CalculatedLineItemScope($calculatedLineItem, $context))->matches()
        );
    }

    public function testRuleWithGreaterThanEqualExactAmountMatch(): void
    {
        $rule = new LineItemUnitPriceRule(100, Rule::OPERATOR_GTE);

        $calculatedLineItem = Generator::createCalculatedProduct('A', 100, 2);
        $context = $this->createMock(CustomerContext::class);

        $this->assertTrue(
            $rule->match(new CalculatedLineItemScope($calculatedLineItem, $context))->matches()
        );
    }

    public function testRuleWithGreaterThanEqualMatch(): void
    {
        $rule = new LineItemUnitPriceRule(99, Rule::OPERATOR_GTE);

        $calculatedLineItem = Generator::createCalculatedProduct('A', 100, 2);
        $context = $this->createMock(CustomerContext::class);

        $this->assertTrue(
            $rule->match(new CalculatedLineItemScope($calculatedLineItem, $context))->matches()
        );
    }

    public function testRuleWithGreaterThanEqualNotMatch(): void
    {
        $rule = new LineItemUnitPriceRule(101, Rule::OPERATOR_GTE);

        $calculatedLineItem = Generator::createCalculatedProduct('A', 100, 2);
        $context = $this->createMock(CustomerContext::class);

        $this->assertFalse(
            $rule->match(new CalculatedLineItemScope($calculatedLineItem, $context))->matches()
        );
    }

    public function testRuleWithNotEqualMatch(): void
    {
        $rule = new LineItemUnitPriceRule(101, Rule::OPERATOR_NEQ);

        $calculatedLineItem = Generator::createCalculatedProduct('A', 100, 2);
        $context = $this->createMock(CustomerContext::class);

        $this->assertTrue(
            $rule->match(new CalculatedLineItemScope($calculatedLineItem, $context))->matches()
        );
    }

    public function testRuleWithNotEqualNotMatch(): void
    {
        $rule = new LineItemUnitPriceRule(100, Rule::OPERATOR_NEQ);

        $calculatedLineItem = Generator::createCalculatedProduct('A', 100, 2);
        $context = $this->createMock(CustomerContext::class);

        $this->assertFalse(
            $rule->match(new CalculatedLineItemScope($calculatedLineItem, $context))->matches()
        );
    }
}
