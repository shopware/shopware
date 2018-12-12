<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\DiscountSurcharge\Rule\Context;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart\Cart;
use Shopware\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Rule\LastNameRule;

class LastNameRuleTest extends TestCase
{
    public function testExactMatch(): void
    {
        $rule = new LastNameRule('shopware');

        $cart = $this->createMock(Cart::class);

        $customer = new CustomerEntity();
        $customer->setLastName('shopware');

        $context = $this->createMock(CheckoutContext::class);

        $context
            ->method('getCustomer')
            ->will(static::returnValue($customer));

        static::assertTrue(
            $rule->match(new CartRuleScope($cart, $context))->matches()
        );
    }

    public function testCaseInsensitive(): void
    {
        $rule = new LastNameRule('SHOPWARE');

        $cart = $this->createMock(Cart::class);

        $customer = new CustomerEntity();
        $customer->setLastName('ShopWare');

        $context = $this->createMock(CheckoutContext::class);

        $context
            ->method('getCustomer')
            ->will(static::returnValue($customer));

        static::assertTrue(
            $rule->match(new CartRuleScope($cart, $context))->matches()
        );
    }

    public function testContains(): void
    {
        $rule = new LastNameRule('olor');

        $cart = $this->createMock(Cart::class);

        $customer = new CustomerEntity();
        $customer->setLastName('dolore magna aliquyam');

        $context = $this->createMock(CheckoutContext::class);

        $context
            ->method('getCustomer')
            ->will(static::returnValue($customer));

        static::assertTrue(
            $rule->match(new CartRuleScope($cart, $context))->matches()
        );
    }

    public function testWithoutCustomer(): void
    {
        $rule = new LastNameRule('test');

        $cart = $this->createMock(Cart::class);

        $context = $this->createMock(CheckoutContext::class);

        $context
            ->method('getCustomer')
            ->will(static::returnValue(null));

        static::assertFalse(
            $rule->match(new CartRuleScope($cart, $context))->matches()
        );
    }
}
