<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Rule\Rule\Context;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Rule\DifferentAddressesRule;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 */
#[Package('business-ops')]
class DifferentAddressesRuleTest extends TestCase
{
    public function testRuleMatch(): void
    {
        $rule = new DifferentAddressesRule();

        $cart = new Cart('test');

        $context = $this->createMock(SalesChannelContext::class);

        $billing = new CustomerAddressEntity();
        $billing->setId('SWAG-CUSTOMER-ADDRESS-ID-1');

        $shipping = new CustomerAddressEntity();
        $shipping->setId('SWAG-CUSTOMER-ADDRESS-ID-2');

        $customer = new CustomerEntity();
        $customer->setDefaultBillingAddress($billing);
        $customer->setDefaultShippingAddress($shipping);

        $context
            ->method('getCustomer')
            ->willReturn($customer);

        static::assertTrue(
            $rule->match(new CartRuleScope($cart, $context))
        );
    }

    public function testRuleNotMatch(): void
    {
        $rule = new DifferentAddressesRule();

        $cart = new Cart('test');

        $context = $this->createMock(SalesChannelContext::class);

        $billing = new CustomerAddressEntity();
        $billing->setId('SWAG-CUSTOMER-ADDRESS-ID-1');

        $shipping = new CustomerAddressEntity();
        $shipping->setId('SWAG-CUSTOMER-ADDRESS-ID-1');

        $customer = new CustomerEntity();
        $customer->setDefaultBillingAddress($billing);
        $customer->setDefaultShippingAddress($shipping);

        $context
            ->method('getCustomer')
            ->willReturn($customer);

        static::assertFalse(
            $rule->match(new CartRuleScope($cart, $context))
        );
    }

    public function testRuleWithoutCustomer(): void
    {
        $rule = new DifferentAddressesRule();

        $cart = new Cart('test');

        $context = $this->createMock(SalesChannelContext::class);

        $context
            ->method('getCustomer')
            ->willReturn(null);

        static::assertFalse(
            $rule->match(new CartRuleScope($cart, $context))
        );
    }
}
