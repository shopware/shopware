<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\DiscountSurcharge\Rule\Cart;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopware\Core\Checkout\Cart\Rule\LineItemsInCartRule;
use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Checkout\Test\Cart\Common\Generator;

class LineItemsInCartRuleTest extends TestCase
{
    public function testRuleWithExactLineItemsMatch(): void
    {
        $rule = new LineItemsInCartRule(['A', 'B']);

        $cart = Generator::createCart();
        $context = $this->createMock(CheckoutContext::class);

        static::assertTrue(
            $rule->match(new CartRuleScope($cart, $context))->matches()
        );
    }

    public function testRuleWithLineItemsNotMatch(): void
    {
        $rule = new LineItemsInCartRule(['C', 'D']);

        $cart = Generator::createCart();
        $context = $this->createMock(CheckoutContext::class);

        static::assertFalse(
            $rule->match(new CartRuleScope($cart, $context))->matches()
        );
    }

    public function testRuleWithLineItemSubsetMatch(): void
    {
        $rule = new LineItemsInCartRule(['B']);

        $cart = Generator::createCart();
        $context = $this->createMock(CheckoutContext::class);

        static::assertTrue(
            $rule->match(new CartRuleScope($cart, $context))->matches()
        );
    }
}
