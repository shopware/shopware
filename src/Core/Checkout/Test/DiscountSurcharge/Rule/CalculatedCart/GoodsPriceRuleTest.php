<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\DiscountSurcharge\Rule\CalculatedCart;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Checkout\Cart\Rule\GoodsPriceRule;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopware\Core\Checkout\Test\Cart\Common\Generator;

class GoodsPriceRuleTest extends TestCase
{
    public function testRuleWithExactPriceMatch(): void
    {
        $rule = new GoodsPriceRule(270, Rule::OPERATOR_EQ);

        $calculatedCart = Generator::createCalculatedCart();
        $context = $this->createMock(CheckoutContext::class);

        $this->assertTrue(
            $rule->match(new CartRuleScope($calculatedCart, $context))->matches()
        );
    }

    public function testRuleWithExactPriceNotMatch(): void
    {
        $rule = new GoodsPriceRule(0, Rule::OPERATOR_EQ);

        $calculatedCart = Generator::createCalculatedCart();
        $context = $this->createMock(CheckoutContext::class);

        $this->assertFalse(
            $rule->match(new CartRuleScope($calculatedCart, $context))->matches()
        );
    }

    public function testRuleWithLowerThanEqualExactPriceMatch(): void
    {
        $rule = new GoodsPriceRule(270, Rule::OPERATOR_LTE);

        $calculatedCart = Generator::createCalculatedCart();
        $context = $this->createMock(CheckoutContext::class);

        $this->assertTrue(
            $rule->match(new CartRuleScope($calculatedCart, $context))->matches()
        );
    }

    public function testRuleWithLowerThanEqualPriceMatch(): void
    {
        $rule = new GoodsPriceRule(300, Rule::OPERATOR_LTE);

        $calculatedCart = Generator::createCalculatedCart();
        $context = $this->createMock(CheckoutContext::class);

        $this->assertTrue(
            $rule->match(new CartRuleScope($calculatedCart, $context))->matches()
        );
    }

    public function testRuleWithLowerThanEqualPriceNotMatch(): void
    {
        $rule = new GoodsPriceRule(250, Rule::OPERATOR_LTE);

        $calculatedCart = Generator::createCalculatedCart();
        $context = $this->createMock(CheckoutContext::class);

        $this->assertFalse(
            $rule->match(new CartRuleScope($calculatedCart, $context))->matches()
        );
    }

    public function testRuleWithGreaterThanEqualExactPriceMatch(): void
    {
        $rule = new GoodsPriceRule(270, Rule::OPERATOR_GTE);

        $calculatedCart = Generator::createCalculatedCart();
        $context = $this->createMock(CheckoutContext::class);

        $this->assertTrue(
            $rule->match(new CartRuleScope($calculatedCart, $context))->matches()
        );
    }

    public function testRuleWithGreaterThanEqualPriceMatch(): void
    {
        $rule = new GoodsPriceRule(250, Rule::OPERATOR_GTE);

        $calculatedCart = Generator::createCalculatedCart();
        $context = $this->createMock(CheckoutContext::class);

        $this->assertTrue(
            $rule->match(new CartRuleScope($calculatedCart, $context))->matches()
        );
    }

    public function testRuleWithGreaterThanEqualPriceNotMatch(): void
    {
        $rule = new GoodsPriceRule(300, Rule::OPERATOR_GTE);

        $calculatedCart = Generator::createCalculatedCart();
        $context = $this->createMock(CheckoutContext::class);

        $this->assertFalse(
            $rule->match(new CartRuleScope($calculatedCart, $context))->matches()
        );
    }

    public function testRuleWithNotEqualPriceMatch(): void
    {
        $rule = new GoodsPriceRule(200, Rule::OPERATOR_NEQ);

        $calculatedCart = Generator::createCalculatedCart();
        $context = $this->createMock(CheckoutContext::class);

        $this->assertTrue(
            $rule->match(new CartRuleScope($calculatedCart, $context))->matches()
        );
    }

    public function testRuleWithNotEqualPriceNotMatch(): void
    {
        $rule = new GoodsPriceRule(270, Rule::OPERATOR_NEQ);

        $calculatedCart = Generator::createCalculatedCart();
        $context = $this->createMock(CheckoutContext::class);

        $this->assertFalse(
            $rule->match(new CartRuleScope($calculatedCart, $context))->matches()
        );
    }
}
