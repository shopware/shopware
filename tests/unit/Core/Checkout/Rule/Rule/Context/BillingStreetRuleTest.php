<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Rule\Rule\Context;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\CustomerException;
use Shopware\Core\Checkout\Customer\Rule\BillingStreetRule;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Exception\UnsupportedValueException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
#[CoversClass(BillingStreetRule::class)]
class BillingStreetRuleTest extends TestCase
{
    public function testWithExactMatch(): void
    {
        $rule = (new BillingStreetRule())->assign(['streetName' => 'example street']);

        $cart = new Cart('test');

        $context = $this->createMock(SalesChannelContext::class);

        $billing = new CustomerAddressEntity();
        $billing->setStreet('example street');

        $customer = new CustomerEntity();
        $customer->setDefaultBillingAddress($billing);

        $context
            ->method('getCustomer')
            ->willReturn($customer);

        static::assertTrue(
            $rule->match(new CartRuleScope($cart, $context))
        );
    }

    public function testCaseInsensitive(): void
    {
        $rule = (new BillingStreetRule())->assign(['streetName' => 'ExaMple StreEt']);

        $cart = new Cart('test');

        $context = $this->createMock(SalesChannelContext::class);

        $billing = new CustomerAddressEntity();
        $billing->setStreet('example street');

        $customer = new CustomerEntity();
        $customer->setDefaultBillingAddress($billing);

        $context
            ->method('getCustomer')
            ->willReturn($customer);

        static::assertTrue(
            $rule->match(new CartRuleScope($cart, $context))
        );
    }

    public function testNotMatch(): void
    {
        $rule = (new BillingStreetRule())->assign(['streetName' => 'example street']);

        $cart = new Cart('test');

        $context = $this->createMock(SalesChannelContext::class);

        $billing = new CustomerAddressEntity();
        $billing->setStreet('test street');

        $customer = new CustomerEntity();
        $customer->setDefaultBillingAddress($billing);

        $context
            ->method('getCustomer')
            ->willReturn($customer);

        static::assertFalse(
            $rule->match(new CartRuleScope($cart, $context))
        );
    }

    public function testWithoutAddress(): void
    {
        $rule = (new BillingStreetRule())->assign(['streetName' => '.ExaMple StreEt']);

        $cart = new Cart('test');

        $context = $this->createMock(SalesChannelContext::class);

        $context
            ->method('getCustomer')
            ->willReturn(null);

        static::assertFalse(
            $rule->match(new CartRuleScope($cart, $context))
        );
    }

    public function testMatchThrowsException(): void
    {
        if (!Feature::isActive('v6.8.0.0')) {
            $this->expectException(UnsupportedValueException::class);
        } else {
            $this->expectException(CustomerException::class);
        }

        $context = $this->createMock(SalesChannelContext::class);

        $billing = new CustomerAddressEntity();
        $billing->setStreet('test street');

        $customer = new CustomerEntity();
        $customer->setDefaultBillingAddress($billing);

        $context
            ->method('getCustomer')
            ->willReturn($customer);

        (new BillingStreetRule())->match(
            new CartRuleScope(
                new Cart('test'),
                $context
            )
        );
    }
}
