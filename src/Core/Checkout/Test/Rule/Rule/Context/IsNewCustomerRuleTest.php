<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Rule\Rule\Context;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Rule\IsNewCustomerRule;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class IsNewCustomerRuleTest extends TestCase
{
    public function testIsNewCustomer(): void
    {
        $rule = new IsNewCustomerRule();

        $cart = $this->createMock(Cart::class);

        $customer = new CustomerEntity();
        $customer->setFirstLogin(new \DateTime());

        $context = $this->createMock(SalesChannelContext::class);

        $context
            ->method('getCustomer')
            ->willReturn($customer);

        static::assertTrue(
            $rule->match(new CartRuleScope($cart, $context))
        );
    }

    public function testIsNotNewCustomer(): void
    {
        $rule = new IsNewCustomerRule();

        $cart = $this->createMock(Cart::class);

        $customer = new CustomerEntity();
        $customer->setFirstLogin(
            (new \DateTime())->sub(
                new \DateInterval('P' . 10 . 'D')
            )
        );

        $context = $this->createMock(SalesChannelContext::class);

        $context
            ->method('getCustomer')
            ->willReturn($customer);

        static::assertFalse(
            $rule->match(new CartRuleScope($cart, $context))
        );
    }

    public function testWithFutureDate(): void
    {
        $rule = new IsNewCustomerRule();

        $cart = $this->createMock(Cart::class);

        $customer = new CustomerEntity();
        $customer->setFirstLogin(
            (new \DateTime())->add(
                new \DateInterval('P' . 10 . 'D')
            )
        );

        $context = $this->createMock(SalesChannelContext::class);

        $context
            ->method('getCustomer')
            ->willReturn($customer);

        static::assertFalse(
            $rule->match(new CartRuleScope($cart, $context))
        );
    }

    public function testWithoutCustomer(): void
    {
        $rule = new IsNewCustomerRule();

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
