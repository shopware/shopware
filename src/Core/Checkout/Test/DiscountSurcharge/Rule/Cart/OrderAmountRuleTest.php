<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\DiscountSurcharge\Rule\Cart;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopware\Core\Checkout\Cart\Rule\OrderAmountRule;
use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Checkout\Test\Cart\Common\Generator;

class OrderAmountRuleTest extends TestCase
{
    public function testRuleWithExactAmountMatch(): void
    {
        $rule = new OrderAmountRule(275, OrderAmountRule::OPERATOR_EQ);

        $cart = Generator::createCart();
        $context = $this->createMock(CheckoutContext::class);

        static::assertTrue(
            $rule->match(new CartRuleScope($cart, $context))->matches()
        );
    }

    public function testRuleWithExactAmountNotMatch(): void
    {
        $rule = new OrderAmountRule(0, OrderAmountRule::OPERATOR_EQ);

        $cart = Generator::createCart();
        $context = $this->createMock(CheckoutContext::class);

        static::assertFalse(
            $rule->match(new CartRuleScope($cart, $context))->matches()
        );
    }

    public function testRuleWithLowerThanEqualExactAmountMatch(): void
    {
        $rule = new OrderAmountRule(275, OrderAmountRule::OPERATOR_LTE);

        $cart = Generator::createCart();
        $context = $this->createMock(CheckoutContext::class);

        static::assertTrue(
            $rule->match(new CartRuleScope($cart, $context))->matches()
        );
    }

    public function testRuleWithLowerThanEqualAmountMatch(): void
    {
        $rule = new OrderAmountRule(300, OrderAmountRule::OPERATOR_LTE);

        $cart = Generator::createCart();
        $context = $this->createMock(CheckoutContext::class);

        static::assertTrue(
            $rule->match(new CartRuleScope($cart, $context))->matches()
        );
    }

    public function testRuleWithLowerThanEqualAmountNotMatch(): void
    {
        $rule = new OrderAmountRule(274, OrderAmountRule::OPERATOR_LTE);

        $cart = Generator::createCart();
        $context = $this->createMock(CheckoutContext::class);

        static::assertFalse(
            $rule->match(new CartRuleScope($cart, $context))->matches()
        );
    }

    public function testRuleWithGreaterThanEqualExactAmountMatch(): void
    {
        $rule = new OrderAmountRule(275, OrderAmountRule::OPERATOR_GTE);

        $cart = Generator::createCart();
        $context = $this->createMock(CheckoutContext::class);

        static::assertTrue(
            $rule->match(new CartRuleScope($cart, $context))->matches()
        );
    }

    public function testRuleWithGreaterThanEqualAmountMatch(): void
    {
        $rule = new OrderAmountRule(100, OrderAmountRule::OPERATOR_GTE);

        $cart = Generator::createCart();
        $context = $this->createMock(CheckoutContext::class);

        static::assertTrue(
            $rule->match(new CartRuleScope($cart, $context))->matches()
        );
    }

    public function testRuleWithGreaterThanEqualAmountNotMatch(): void
    {
        $rule = new OrderAmountRule(276, OrderAmountRule::OPERATOR_GTE);

        $cart = Generator::createCart();
        $context = $this->createMock(CheckoutContext::class);

        static::assertFalse(
            $rule->match(new CartRuleScope($cart, $context))->matches()
        );
    }

    public function testRuleNotEqualAmountMatch(): void
    {
        $rule = new OrderAmountRule(0, OrderAmountRule::OPERATOR_NEQ);

        $cart = Generator::createCart();
        $context = $this->createMock(CheckoutContext::class);

        static::assertTrue(
            $rule->match(new CartRuleScope($cart, $context))->matches()
        );
    }

    public function testRuleNotEqualAmountNotMatch(): void
    {
        $rule = new OrderAmountRule(275, OrderAmountRule::OPERATOR_NEQ);

        $cart = Generator::createCart();
        $context = $this->createMock(CheckoutContext::class);

        static::assertFalse(
            $rule->match(new CartRuleScope($cart, $context))->matches()
        );
    }

    /**
     * @dataProvider unsupportedOperators
     *
     * @expectedException \Shopware\Core\Content\Rule\Exception\UnsupportedOperatorException
     *
     * @param string $operator
     */
    public function testUnsupportedOperators(string $operator): void
    {
        $rule = new OrderAmountRule(100, $operator);

        $cart = Generator::createCart();
        $context = $this->createMock(CheckoutContext::class);

        static::assertFalse(
            $rule->match(new CartRuleScope($cart, $context))->matches()
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
