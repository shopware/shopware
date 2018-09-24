<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\DiscountSurcharge\Rule\Context;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart\Cart;
use Shopware\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Rule\CurrencyRule;

class CurrencyRuleTest extends TestCase
{
    public function testMatch(): void
    {
        $rule = new CurrencyRule(['SWAG-CURRENCY-ID-1']);

        $cart = $this->createMock(Cart::class);

        $checkoutContext = $this->createMock(CheckoutContext::class);

        $context = $this->createMock(Context::class);
        $context
            ->method('getCurrencyId')
            ->will(static::returnValue('SWAG-CURRENCY-ID-1'));

        $checkoutContext
            ->method('getContext')
            ->will(static::returnValue($context));

        static::assertTrue(
            $rule->match(new CartRuleScope($cart, $checkoutContext))->matches()
        );
    }

    public function testNotMatch(): void
    {
        $rule = new CurrencyRule(['SWAG-CURRENCY-ID-2', 'SWAG-CURRENCY-ID-3', 'SWAG-CURRENCY-ID-1']);

        $cart = $this->createMock(Cart::class);

        $checkoutContext = $this->createMock(CheckoutContext::class);

        $context = $this->createMock(Context::class);
        $context
            ->method('getCurrencyId')
            ->will(static::returnValue('SWAG-CURRENCY-ID-5'));

        $checkoutContext
            ->method('getContext')
            ->will(static::returnValue($context));

        static::assertFalse(
            $rule->match(new CartRuleScope($cart, $checkoutContext))->matches()
        );
    }

    public function testMultipleCurrencies(): void
    {
        $rule = new CurrencyRule(['SWAG-CURRENCY-ID-2', 'SWAG-CURRENCY-ID-3', 'SWAG-CURRENCY-ID-1']);

        $cart = $this->createMock(Cart::class);

        $checkoutContext = $this->createMock(CheckoutContext::class);

        $context = $this->createMock(Context::class);
        $context
            ->method('getCurrencyId')
            ->will(static::returnValue('SWAG-CURRENCY-ID-3'));

        $checkoutContext
            ->method('getContext')
            ->will(static::returnValue($context));

        static::assertTrue(
            $rule->match(new CartRuleScope($cart, $checkoutContext))->matches()
        );
    }
}
