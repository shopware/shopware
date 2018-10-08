<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\DiscountSurcharge\Rule\Cart;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Rule\CartAmountRule;
use Shopware\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Checkout\Test\Cart\Common\Generator;

class CartAmountRuleTest extends TestCase
{
    public function testRuleWithExactAmountMatch(): void
    {
        $rule = new CartAmountRule(275, CartAmountRule::OPERATOR_EQ);

        $cart = Generator::createCart();
        $context = $this->createMock(CheckoutContext::class);

        static::assertTrue(
            $rule->match(new CartRuleScope($cart, $context))->matches()
        );
    }

    public function testRuleWithExactAmountNotMatch(): void
    {
        $rule = new CartAmountRule(0, CartAmountRule::OPERATOR_EQ);

        $cart = Generator::createCart();
        $context = $this->createMock(CheckoutContext::class);

        static::assertFalse(
            $rule->match(new CartRuleScope($cart, $context))->matches()
        );
    }

    public function testRuleWithLowerThanEqualExactAmountMatch(): void
    {
        $rule = new CartAmountRule(275, CartAmountRule::OPERATOR_LTE);

        $cart = Generator::createCart();
        $context = $this->createMock(CheckoutContext::class);

        static::assertTrue(
            $rule->match(new CartRuleScope($cart, $context))->matches()
        );
    }

    public function testRuleWithLowerThanEqualAmountMatch(): void
    {
        $rule = new CartAmountRule(300, CartAmountRule::OPERATOR_LTE);

        $cart = Generator::createCart();
        $context = $this->createMock(CheckoutContext::class);

        static::assertTrue(
            $rule->match(new CartRuleScope($cart, $context))->matches()
        );
    }

    public function testRuleWithLowerThanEqualAmountNotMatch(): void
    {
        $rule = new CartAmountRule(274, CartAmountRule::OPERATOR_LTE);

        $cart = Generator::createCart();
        $context = $this->createMock(CheckoutContext::class);

        static::assertFalse(
            $rule->match(new CartRuleScope($cart, $context))->matches()
        );
    }

    public function testRuleWithGreaterThanEqualExactAmountMatch(): void
    {
        $rule = new CartAmountRule(275, CartAmountRule::OPERATOR_GTE);

        $cart = Generator::createCart();
        $context = $this->createMock(CheckoutContext::class);

        static::assertTrue(
            $rule->match(new CartRuleScope($cart, $context))->matches()
        );
    }

    public function testRuleWithGreaterThanEqualAmountMatch(): void
    {
        $rule = new CartAmountRule(100, CartAmountRule::OPERATOR_GTE);

        $cart = Generator::createCart();
        $context = $this->createMock(CheckoutContext::class);

        static::assertTrue(
            $rule->match(new CartRuleScope($cart, $context))->matches()
        );
    }

    public function testRuleWithGreaterThanEqualAmountNotMatch(): void
    {
        $rule = new CartAmountRule(276, CartAmountRule::OPERATOR_GTE);

        $cart = Generator::createCart();
        $context = $this->createMock(CheckoutContext::class);

        static::assertFalse(
            $rule->match(new CartRuleScope($cart, $context))->matches()
        );
    }

    public function testRuleNotEqualAmountMatch(): void
    {
        $rule = new CartAmountRule(0, CartAmountRule::OPERATOR_NEQ);

        $cart = Generator::createCart();
        $context = $this->createMock(CheckoutContext::class);

        static::assertTrue(
            $rule->match(new CartRuleScope($cart, $context))->matches()
        );
    }

    public function testRuleNotEqualAmountNotMatch(): void
    {
        $rule = new CartAmountRule(275, CartAmountRule::OPERATOR_NEQ);

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
        $rule = new CartAmountRule(100, $operator);

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
