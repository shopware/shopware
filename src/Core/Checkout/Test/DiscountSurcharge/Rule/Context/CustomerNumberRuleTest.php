<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\DiscountSurcharge\Rule\Context;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart\Cart;
use Shopware\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Checkout\Customer\CustomerStruct;
use Shopware\Core\Checkout\Customer\Rule\CustomerNumberRule;

class CustomerNumberRuleTest extends TestCase
{
    public function testExactMatch(): void
    {
        $rule = new CustomerNumberRule(['NO. 1']);

        $cart = $this->createMock(Cart::class);

        $customer = new CustomerStruct();
        $customer->setCustomerNumber('NO. 1');

        $context = $this->createMock(CheckoutContext::class);

        $context->expects(static::any())
            ->method('getCustomer')
            ->will(static::returnValue($customer));

        static::assertTrue(
            $rule->match(new CartRuleScope($cart, $context))->matches()
        );
    }

    public function testMultipleNumbers(): void
    {
        $rule = new CustomerNumberRule(['NO. 1', 'NO. 2', 'NO. 3']);

        $cart = $this->createMock(Cart::class);

        $customer = new CustomerStruct();
        $customer->setCustomerNumber('NO. 2');

        $context = $this->createMock(CheckoutContext::class);

        $context->expects(static::any())
            ->method('getCustomer')
            ->will(static::returnValue($customer));

        static::assertTrue(
            $rule->match(new CartRuleScope($cart, $context))->matches()
        );
    }

    public function testCaseInsensitive(): void
    {
        $rule = new CustomerNumberRule(['NO. 1']);

        $cart = $this->createMock(Cart::class);

        $customer = new CustomerStruct();
        $customer->setCustomerNumber('no. 1');

        $context = $this->createMock(CheckoutContext::class);

        $context->expects(static::any())
            ->method('getCustomer')
            ->will(static::returnValue($customer));

        static::assertTrue(
            $rule->match(new CartRuleScope($cart, $context))->matches()
        );
    }

    public function testWithoutCustomer(): void
    {
        $rule = new CustomerNumberRule(['NO. 1']);

        $cart = $this->createMock(Cart::class);

        $context = $this->createMock(CheckoutContext::class);

        $context->expects(static::any())
            ->method('getCustomer')
            ->will(static::returnValue(null));

        static::assertFalse(
            $rule->match(new CartRuleScope($cart, $context))->matches()
        );
    }

    public function testNotMatch(): void
    {
        $rule = new CustomerNumberRule(['NO. 1']);

        $cart = $this->createMock(Cart::class);

        $customer = new CustomerStruct();
        $customer->setCustomerNumber('no. 2');

        $context = $this->createMock(CheckoutContext::class);

        $context->expects(static::any())
            ->method('getCustomer')
            ->will(static::returnValue($customer));

        static::assertFalse(
            $rule->match(new CartRuleScope($cart, $context))->matches()
        );
    }
}
