<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Rule\Rule\Context;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Rule\LastNameRule;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class LastNameRuleTest extends TestCase
{
    public function testExactMatch(): void
    {
        $rule = (new LastNameRule())->assign(['lastName' => 'shopware']);

        $cart = $this->createMock(Cart::class);

        $customer = new CustomerEntity();
        $customer->setLastName('shopware');

        $context = $this->createMock(SalesChannelContext::class);

        $context
            ->method('getCustomer')
            ->willReturn($customer);

        static::assertTrue(
            $rule->match(new CartRuleScope($cart, $context))
        );
    }

    public function testCaseInsensitive(): void
    {
        $rule = (new LastNameRule())->assign(['lastName' => 'shopware']);

        $cart = $this->createMock(Cart::class);

        $customer = new CustomerEntity();
        $customer->setLastName('ShopWare');

        $context = $this->createMock(SalesChannelContext::class);

        $context
            ->method('getCustomer')
            ->willReturn($customer);

        static::assertTrue(
            $rule->match(new CartRuleScope($cart, $context))
        );
    }

    public function testWithoutCustomer(): void
    {
        $rule = new LastNameRule();

        $cart = $this->createMock(Cart::class);

        $context = $this->createMock(SalesChannelContext::class);

        $context
            ->method('getCustomer')
            ->willReturn(null);

        static::assertFalse(
            $rule->match(new CartRuleScope($cart, $context))
        );
    }
}
