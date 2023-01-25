<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Rule\Rule\Context;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Rule\CustomerNumberRule;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 */
#[Package('business-ops')]
class CustomerNumberRuleTest extends TestCase
{
    public function testExactMatch(): void
    {
        $rule = (new CustomerNumberRule())->assign(['numbers' => ['NO. 1']]);

        $cart = new Cart('test');

        $customer = new CustomerEntity();
        $customer->setCustomerNumber('NO. 1');

        $context = $this->createMock(SalesChannelContext::class);

        $context
            ->method('getCustomer')
            ->willReturn($customer);

        static::assertTrue(
            $rule->match(new CartRuleScope($cart, $context))
        );
    }

    public function testMultipleNumbers(): void
    {
        $rule = (new CustomerNumberRule())->assign(['numbers' => ['NO. 1', 'NO. 2', 'NO. 3']]);

        $cart = new Cart('test');

        $customer = new CustomerEntity();
        $customer->setCustomerNumber('NO. 2');

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
        $rule = (new CustomerNumberRule())->assign(['numbers' => ['NO. 1']]);

        $cart = new Cart('test');

        $customer = new CustomerEntity();
        $customer->setCustomerNumber('no. 1');

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
        $rule = (new CustomerNumberRule())->assign(['numbers' => ['NO. 1']]);

        $cart = new Cart('test');

        $context = $this->createMock(SalesChannelContext::class);

        $context
            ->method('getCustomer')
            ->willReturn(null);

        static::assertFalse(
            $rule->match(new CartRuleScope($cart, $context))
        );
    }

    public function testNotMatch(): void
    {
        $rule = (new CustomerNumberRule())->assign(['numbers' => ['NO. 1']]);

        $cart = new Cart('test');

        $customer = new CustomerEntity();
        $customer->setCustomerNumber('no. 2');

        $context = $this->createMock(SalesChannelContext::class);

        $context
            ->method('getCustomer')
            ->willReturn($customer);

        static::assertFalse(
            $rule->match(new CartRuleScope($cart, $context))
        );
    }
}
