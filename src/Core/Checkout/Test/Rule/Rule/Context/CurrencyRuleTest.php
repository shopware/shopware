<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Rule\Rule\Context;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\Currency\Rule\CurrencyRule;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class CurrencyRuleTest extends TestCase
{
    public function testMatch(): void
    {
        $rule = (new CurrencyRule())->assign(['currencyIds' => ['SWAG-CURRENCY-ID-1']]);

        $cart = $this->createMock(Cart::class);

        $salesChannelContext = $this->createMock(SalesChannelContext::class);

        $context = $this->createMock(Context::class);
        $context
            ->method('getCurrencyId')
            ->willReturn('SWAG-CURRENCY-ID-1');

        $salesChannelContext
            ->method('getContext')
            ->willReturn($context);

        static::assertTrue(
            $rule->match(new CartRuleScope($cart, $salesChannelContext))
        );
    }

    public function testNotMatch(): void
    {
        $rule = (new CurrencyRule())->assign(['currencyIds' => ['SWAG-CURRENCY-ID-2', 'SWAG-CURRENCY-ID-3', 'SWAG-CURRENCY-ID-1']]);

        $cart = $this->createMock(Cart::class);

        $salesChannelContext = $this->createMock(SalesChannelContext::class);

        $context = $this->createMock(Context::class);
        $context
            ->method('getCurrencyId')
            ->willReturn('SWAG-CURRENCY-ID-5');

        $salesChannelContext
            ->method('getContext')
            ->willReturn($context);

        static::assertFalse(
            $rule->match(new CartRuleScope($cart, $salesChannelContext))
        );
    }

    public function testMultipleCurrencies(): void
    {
        $rule = (new CurrencyRule())->assign(['currencyIds' => ['SWAG-CURRENCY-ID-2', 'SWAG-CURRENCY-ID-3', 'SWAG-CURRENCY-ID-1']]);

        $cart = $this->createMock(Cart::class);

        $salesChannelContext = $this->createMock(SalesChannelContext::class);

        $context = $this->createMock(Context::class);
        $context
            ->method('getCurrencyId')
            ->willReturn('SWAG-CURRENCY-ID-3');

        $salesChannelContext
            ->method('getContext')
            ->willReturn($context);

        static::assertTrue(
            $rule->match(new CartRuleScope($cart, $salesChannelContext))
        );
    }
}
