<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\DiscountSurcharge\Rule\Cart;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopware\Core\Checkout\Cart\Rule\GoodsCountRule;
use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Framework\Rule\Rule;

class GoodsCountRuleTest extends TestCase
{
    public function testRuleWithExactCountMatch(): void
    {
        $rule = (new GoodsCountRule())->assign(['count' => 0, 'operator' => Rule::OPERATOR_EQ]);

        $cart = new Cart('test', 'test');
        $context = $this->createMock(CheckoutContext::class);

        static::assertTrue(
            $rule->match(new CartRuleScope($cart, $context))->matches()
        );
    }

    public function testRuleWithExactCountNotMatch(): void
    {
        $rule = (new GoodsCountRule())->assign(['count' => 0, 'operator' => Rule::OPERATOR_EQ]);

        $cart = new Cart('test', 'test');
        $context = $this->createMock(CheckoutContext::class);

        static::assertTrue(
            $rule->match(new CartRuleScope($cart, $context))->matches()
        );
    }

    public function testRuleWithLowerThanEqualExactCountMatch(): void
    {
        $rule = (new GoodsCountRule())->assign(['count' => 1, 'operator' => Rule::OPERATOR_LTE]);

        $cart = new Cart('test', 'test');
        $context = $this->createMock(CheckoutContext::class);

        static::assertTrue(
            $rule->match(new CartRuleScope($cart, $context))->matches()
        );
    }

    public function testRuleWithLowerThanEqualCountMatch(): void
    {
        $rule = (new GoodsCountRule())->assign(['count' => 2, 'operator' => Rule::OPERATOR_LTE]);

        $cart = new Cart('test', 'test');
        $context = $this->createMock(CheckoutContext::class);

        static::assertTrue(
            $rule->match(new CartRuleScope($cart, $context))->matches()
        );
    }

    public function testRuleWithLowerThanEqualCountNotMatch(): void
    {
        $rule = (new GoodsCountRule())->assign(['count' => 0, 'operator' => Rule::OPERATOR_LTE]);

        $cart = new Cart('test', 'test');

        $cart->add((new LineItem('A', 'test'))->setGood(true));

        $context = $this->createMock(CheckoutContext::class);

        static::assertFalse(
            $rule->match(new CartRuleScope($cart, $context))->matches()
        );
    }

    public function testRuleWithGreaterThanEqualExactCountMatch(): void
    {
        $rule = (new GoodsCountRule())->assign(['count' => 1, 'operator' => Rule::OPERATOR_GTE]);

        $cart = new Cart('test', 'test');
        $cart->add((new LineItem('a', 'a'))->setGood(true));
        $context = $this->createMock(CheckoutContext::class);

        static::assertTrue(
            $rule->match(new CartRuleScope($cart, $context))->matches()
        );
    }

    public function testRuleWithGreaterThanEqualCountMatch(): void
    {
        $rule = (new GoodsCountRule())->assign(['count' => 0, 'operator' => Rule::OPERATOR_GTE]);

        $cart = new Cart('test', 'test');
        $context = $this->createMock(CheckoutContext::class);

        static::assertTrue(
            $rule->match(new CartRuleScope($cart, $context))->matches()
        );
    }

    public function testRuleWithGreaterThanEqualCountNotMatch(): void
    {
        $rule = (new GoodsCountRule())->assign(['count' => 2, 'operator' => Rule::OPERATOR_GTE]);

        $cart = new Cart('test', 'test');
        $context = $this->createMock(CheckoutContext::class);

        static::assertFalse(
            $rule->match(new CartRuleScope($cart, $context))->matches()
        );
    }

    public function testRuleWithNotEqualCountMatch(): void
    {
        $rule = (new GoodsCountRule())->assign(['count' => 2, 'operator' => Rule::OPERATOR_NEQ]);

        $cart = new Cart('test', 'test');
        $context = $this->createMock(CheckoutContext::class);

        static::assertTrue(
            $rule->match(new CartRuleScope($cart, $context))->matches()
        );
    }

    public function testRuleWithNotEqualCountNotMatch(): void
    {
        $rule = (new GoodsCountRule())->assign(['count' => 1, 'operator' => Rule::OPERATOR_NEQ]);

        $cart = new Cart('test', 'test');
        $cart->add((new LineItem('a', 'a'))->setGood(true));

        $context = $this->createMock(CheckoutContext::class);

        static::assertFalse(
            $rule->match(new CartRuleScope($cart, $context))->matches()
        );
    }
}
