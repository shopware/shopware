<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\DiscountSurcharge\Rule\Context;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart\Cart;
use Shopware\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressStruct;
use Shopware\Core\Checkout\Customer\CustomerStruct;
use Shopware\Core\Checkout\Customer\Rule\DifferentAddressesRule;

class DifferentAddressesRuleTest extends TestCase
{
    public function testRuleMatch(): void
    {
        $rule = new DifferentAddressesRule();

        $cart = $this->createMock(Cart::class);

        $context = $this->createMock(CheckoutContext::class);

        $billing = new CustomerAddressStruct();
        $billing->setId('SWAG-CUSTOMER-ADDRESS-ID-1');

        $shipping = new CustomerAddressStruct();
        $shipping->setId('SWAG-CUSTOMER-ADDRESS-ID-2');

        $customer = new CustomerStruct();
        $customer->setDefaultBillingAddress($billing);
        $customer->setDefaultShippingAddress($shipping);

        $context->expects(static::any())
            ->method('getCustomer')
            ->will(static::returnValue($customer));

        static::assertTrue(
            $rule->match(new CartRuleScope($cart, $context))->matches()
        );
    }

    public function testRuleNotMatch(): void
    {
        $rule = new DifferentAddressesRule();

        $cart = $this->createMock(Cart::class);

        $context = $this->createMock(CheckoutContext::class);

        $billing = new CustomerAddressStruct();
        $billing->setId('SWAG-CUSTOMER-ADDRESS-ID-1');

        $shipping = new CustomerAddressStruct();
        $shipping->setId('SWAG-CUSTOMER-ADDRESS-ID-1');

        $customer = new CustomerStruct();
        $customer->setDefaultBillingAddress($billing);
        $customer->setDefaultShippingAddress($shipping);

        $context->expects(static::any())
            ->method('getCustomer')
            ->will(static::returnValue($customer));

        static::assertFalse(
            $rule->match(new CartRuleScope($cart, $context))->matches()
        );
    }

    public function testRuleWithoutCustomer(): void
    {
        $rule = new DifferentAddressesRule();

        $cart = $this->createMock(Cart::class);

        $context = $this->createMock(CheckoutContext::class);

        $context->expects(static::any())
            ->method('getCustomer')
            ->will(static::returnValue(null));

        static::assertFalse(
            $rule->match(new CartRuleScope($cart, $context))->matches()
        );
    }
}
