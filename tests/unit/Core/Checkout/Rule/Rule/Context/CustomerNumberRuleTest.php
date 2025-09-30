<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Rule\Rule\Context;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\CustomerException;
use Shopware\Core\Checkout\Customer\Rule\CustomerNumberRule;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Exception\UnsupportedValueException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
#[CoversClass(CustomerNumberRule::class)]
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

    public function testMatchThrowsException(): void
    {
        if (!Feature::isActive('v6.8.0.0')) {
            $this->expectException(UnsupportedValueException::class);
        } else {
            $this->expectException(CustomerException::class);
        }

        $context = $this->createMock(SalesChannelContext::class);

        $context
            ->method('getCustomer')
            ->willReturn(new CustomerEntity());

        (new CustomerNumberRule())->match(
            new CartRuleScope(
                new Cart('test'),
                $context
            )
        );
    }
}
