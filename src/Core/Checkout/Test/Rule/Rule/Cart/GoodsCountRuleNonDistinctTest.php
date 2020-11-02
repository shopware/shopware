<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Rule\Rule\Cart;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopware\Core\Checkout\Cart\Rule\GoodsCountRuleNonDistinct;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class GoodsCountRuleNonDistinctTest extends TestCase
{
    public function testRuleWithExactCountMatch(): void
    {
        $rule = (new GoodsCountRuleNonDistinct())->assign(['count' => 0, 'operator' => Rule::OPERATOR_EQ]);

        $cart = new Cart('test', 'test');
        $context = $this->createMock(SalesChannelContext::class);

        static::assertTrue(
            $rule->match(new CartRuleScope($cart, $context))
        );
    }

    public function testRuleWithExactCountNotMatch(): void
    {
        $rule = (new GoodsCountRuleNonDistinct())->assign(['count' => 0, 'operator' => Rule::OPERATOR_EQ]);

        $cart = new Cart('test', 'test');
        $context = $this->createMock(SalesChannelContext::class);

        static::assertTrue(
            $rule->match(new CartRuleScope($cart, $context))
        );
    }

    public function testRuleWithLowerThanEqualExactCountMatch(): void
    {
        $rule = (new GoodsCountRuleNonDistinct())->assign(['count' => 1, 'operator' => Rule::OPERATOR_LTE]);

        $cart = new Cart('test', 'test');
        $context = $this->createMock(SalesChannelContext::class);

        static::assertTrue(
            $rule->match(new CartRuleScope($cart, $context))
        );
    }

    public function testRuleWithLowerThanEqualCountMatch(): void
    {
        $rule = (new GoodsCountRuleNonDistinct())->assign(['count' => 2, 'operator' => Rule::OPERATOR_LTE]);

        $cart = new Cart('test', 'test');
        $context = $this->createMock(SalesChannelContext::class);

        static::assertTrue(
            $rule->match(new CartRuleScope($cart, $context))
        );
    }

    public function testRuleWithLowerThanEqualCountNotMatch(): void
    {
        $rule = (new GoodsCountRuleNonDistinct())->assign(['count' => 0, 'operator' => Rule::OPERATOR_LTE]);

        $cart = new Cart('test', 'test');

        $cart->add((new LineItem('A', 'test'))->setGood(true));

        $context = $this->createMock(SalesChannelContext::class);

        static::assertFalse(
            $rule->match(new CartRuleScope($cart, $context))
        );
    }

    public function testRuleWithGreaterThanEqualExactCountMatch(): void
    {
        $rule = (new GoodsCountRuleNonDistinct())->assign(['count' => 1, 'operator' => Rule::OPERATOR_GTE]);

        $cart = new Cart('test', 'test');
        $cart->add((new LineItem('a', 'a'))->setGood(true));
        $context = $this->createMock(SalesChannelContext::class);

        static::assertTrue(
            $rule->match(new CartRuleScope($cart, $context))
        );
    }

    public function testRuleWithGreaterThanEqualCountMatch(): void
    {
        $rule = (new GoodsCountRuleNonDistinct())->assign(['count' => 0, 'operator' => Rule::OPERATOR_GTE]);

        $cart = new Cart('test', 'test');
        $context = $this->createMock(SalesChannelContext::class);

        static::assertTrue(
            $rule->match(new CartRuleScope($cart, $context))
        );
    }

    public function testRuleWithGreaterThanEqualCountNotMatch(): void
    {
        $rule = (new GoodsCountRuleNonDistinct())->assign(['count' => 2, 'operator' => Rule::OPERATOR_GTE]);

        $cart = new Cart('test', 'test');
        $context = $this->createMock(SalesChannelContext::class);

        static::assertFalse(
            $rule->match(new CartRuleScope($cart, $context))
        );
    }

    public function testRuleWithNotEqualCountMatch(): void
    {
        $rule = (new GoodsCountRuleNonDistinct())->assign(['count' => 2, 'operator' => Rule::OPERATOR_NEQ]);

        $cart = new Cart('test', 'test');
        $context = $this->createMock(SalesChannelContext::class);

        static::assertTrue(
            $rule->match(new CartRuleScope($cart, $context))
        );
    }

    public function testRuleWithNotEqualCountNotMatch(): void
    {
        $rule = (new GoodsCountRuleNonDistinct())->assign(['count' => 1, 'operator' => Rule::OPERATOR_NEQ]);

        $cart = new Cart('test', 'test');
        $cart->add((new LineItem('a', 'a'))->setGood(true));

        $context = $this->createMock(SalesChannelContext::class);

        static::assertFalse(
            $rule->match(new CartRuleScope($cart, $context))
        );
    }
}
