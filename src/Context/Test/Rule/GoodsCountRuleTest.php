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

namespace Shopware\Context\Test\Rule;

use PHPUnit\Framework\TestCase;
use Shopware\Cart\Cart\Struct\CalculatedCart;
use Shopware\Cart\LineItem\CalculatedLineItem;
use Shopware\Cart\LineItem\CalculatedLineItemCollection;
use Shopware\Cart\LineItem\GoodsInterface;
use Shopware\Cart\LineItem\LineItem;
use Shopware\Cart\Price\Struct\Price;
use Shopware\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\CartBridge\Voucher\Struct\CalculatedVoucher;
use Shopware\CartBridge\Voucher\VoucherProcessor;
use Shopware\Context\Rule\Container\AndRule;
use Shopware\Context\Rule\GoodsCountRule;
use Shopware\Context\Rule\Rule;
use Shopware\Context\Struct\StorefrontContext;
use Shopware\Framework\Struct\StructCollection;

class GoodsCountRuleTest extends TestCase
{
    public function testGteExactMatch(): void
    {
        $rule = new GoodsCountRule(2, GoodsCountRule::OPERATOR_GTE);

        $cart = $this->createMock(CalculatedCart::class);

        $cart->expects($this->any())
            ->method('getCalculatedLineItems')
            ->will($this->returnValue(
                new CalculatedLineItemCollection([
                    new ItemForGoodsRule('SW1'),
                    new ItemForGoodsRule('SW2'),
                ])
            ));

        $context = $this->createMock(StorefrontContext::class);

        $this->assertTrue(
            $rule->match($cart, $context, new StructCollection())->matches()
        );
    }

    public function testGteWithVoucher(): void
    {
        $rule = new GoodsCountRule(2, GoodsCountRule::OPERATOR_GTE);

        $cart = $this->createMock(CalculatedCart::class);

        $cart->expects($this->any())
            ->method('getCalculatedLineItems')
            ->will($this->returnValue(
                new CalculatedLineItemCollection([
                    new ItemForGoodsRule('SW1'),
                    new ItemForGoodsRule('SW2'),
                    new CalculatedVoucher(
                        'Code1',
                        new LineItem('1', VoucherProcessor::TYPE_VOUCHER, 1),
                        new Price(-1, -1, new CalculatedTaxCollection(), new TaxRuleCollection()),
                        'voucher',
                        new AndRule()
                    ),
                ])
            ));

        $context = $this->createMock(StorefrontContext::class);

        $this->assertTrue(
            $rule->match($cart, $context, new StructCollection())->matches()
        );
    }

    public function testGteNotMatch(): void
    {
        $rule = new GoodsCountRule(2, GoodsCountRule::OPERATOR_GTE);

        $cart = $this->createMock(CalculatedCart::class);

        $cart->expects($this->any())
            ->method('getCalculatedLineItems')
            ->will($this->returnValue(
                new CalculatedLineItemCollection([
                    new ItemForGoodsRule('SW1'),
                ])
            ));

        $context = $this->createMock(StorefrontContext::class);

        $this->assertFalse(
            $rule->match($cart, $context, new StructCollection())->matches()
        );
    }

    public function testLteExactMatch(): void
    {
        $rule = new GoodsCountRule(2, GoodsCountRule::OPERATOR_LTE);

        $cart = $this->createMock(CalculatedCart::class);

        $cart->expects($this->any())
            ->method('getCalculatedLineItems')
            ->will($this->returnValue(
                new CalculatedLineItemCollection([
                    new ItemForGoodsRule('SW1'),
                    new ItemForGoodsRule('SW2'),
                ])
            ));

        $context = $this->createMock(StorefrontContext::class);

        $this->assertTrue(
            $rule->match($cart, $context, new StructCollection())->matches()
        );
    }

    public function testLteWithVoucher(): void
    {
        $rule = new GoodsCountRule(2, GoodsCountRule::OPERATOR_LTE);

        $cart = $this->createMock(CalculatedCart::class);

        $cart->expects($this->any())
            ->method('getCalculatedLineItems')
            ->will($this->returnValue(
                new CalculatedLineItemCollection([
                    new ItemForGoodsRule('SW1'),
                    new ItemForGoodsRule('SW2'),
                    new CalculatedVoucher(
                        'Code1',
                        new LineItem('1', VoucherProcessor::TYPE_VOUCHER, 1),
                        new Price(-1, -1, new CalculatedTaxCollection(), new TaxRuleCollection()),
                        'voucher',
                        new AndRule()
                    ),
                ])
            ));

        $context = $this->createMock(StorefrontContext::class);

        $this->assertTrue(
            $rule->match($cart, $context, new StructCollection())->matches()
        );
    }

    public function testLteNotMatch(): void
    {
        $rule = new GoodsCountRule(2, GoodsCountRule::OPERATOR_LTE);

        $cart = $this->createMock(CalculatedCart::class);

        $cart->expects($this->any())
            ->method('getCalculatedLineItems')
            ->will($this->returnValue(
                new CalculatedLineItemCollection([
                    new ItemForGoodsRule('SW1'),
                    new ItemForGoodsRule('SW2'),
                    new ItemForGoodsRule('SW3'),
                ])
            ));

        $context = $this->createMock(StorefrontContext::class);

        $this->assertFalse(
            $rule->match($cart, $context, new StructCollection())->matches()
        );
    }

    /**
     * @dataProvider unsupportedOperators
     *
     * @expectedException \Shopware\Context\Rule\Exception\UnsupportedOperatorException
     *
     * @param string $operator
     */
    public function testUnsupportedOperators(string $operator): void
    {
        $rule = new GoodsCountRule(2, $operator);

        $cart = $this->createMock(CalculatedCart::class);

        $context = $this->createMock(StorefrontContext::class);

        $this->assertFalse(
            $rule->match($cart, $context, new StructCollection())->matches()
        );
    }

    public function unsupportedOperators(): array
    {
        return [
            [true],
            [false],
            [''],
            [Rule::OPERATOR_EQ],
            [Rule::OPERATOR_NEQ],
        ];
    }
}

class ItemForGoodsRule extends CalculatedLineItem implements GoodsInterface
{
    public function __construct($identifier)
    {
        parent::__construct(
            $identifier,
            new Price(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection()),
            1,
            'test',
            'test'
        );
    }
}
