<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Rule\Rule\Context;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Rule\IsNewCustomerRule;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @deprecated tag:v6.6.0 - will be removed, use DaysSinceFirstLoginRuleTest instead
 *
 * @internal
 */
#[Package('business-ops')]
class IsNewCustomerRuleTest extends TestCase
{
    public function testIsNewCustomer(): void
    {
        Feature::skipTestIfActive('v6.6.0.0', $this);

        $rule = new IsNewCustomerRule();

        $cart = new Cart('test');

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
        Feature::skipTestIfActive('v6.6.0.0', $this);

        $rule = new IsNewCustomerRule();

        $cart = new Cart('test');

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
        Feature::skipTestIfActive('v6.6.0.0', $this);

        $rule = new IsNewCustomerRule();

        $cart = new Cart('test');

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
        Feature::skipTestIfActive('v6.6.0.0', $this);

        $rule = new IsNewCustomerRule();

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
