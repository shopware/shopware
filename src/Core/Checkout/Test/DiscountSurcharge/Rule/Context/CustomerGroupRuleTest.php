<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\DiscountSurcharge\Rule\Context;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart\Cart;
use Shopware\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupStruct;
use Shopware\Core\Checkout\Customer\Rule\CustomerGroupRule;

class CustomerGroupRuleTest extends TestCase
{
    public function testMatch(): void
    {
        $rule = new CustomerGroupRule(['SWAG-CUSTOMER-GROUP-ID-1']);

        $cart = $this->createMock(Cart::class);

        $group = new CustomerGroupStruct();
        $group->setId('SWAG-CUSTOMER-GROUP-ID-1');

        $context = $this->createMock(CheckoutContext::class);

        $context
            ->method('getCurrentCustomerGroup')
            ->will(static::returnValue($group));

        static::assertTrue(
            $rule->match(new CartRuleScope($cart, $context))->matches()
        );
    }

    public function testMultipleGroups(): void
    {
        $rule = new CustomerGroupRule(['SWAG-CUSTOMER-GROUP-ID-2', 'SWAG-CUSTOMER-GROUP-ID-3', 'SWAG-CUSTOMER-GROUP-ID-1']);

        $cart = $this->createMock(Cart::class);

        $group = new CustomerGroupStruct();
        $group->setId('SWAG-CUSTOMER-GROUP-ID-3');

        $context = $this->createMock(CheckoutContext::class);

        $context
            ->method('getCurrentCustomerGroup')
            ->will(static::returnValue($group));

        static::assertTrue(
            $rule->match(new CartRuleScope($cart, $context))->matches()
        );
    }

    public function testNotMatch(): void
    {
        $rule = new CustomerGroupRule(['SWAG-CUSTOMER-GROUP-ID-2', 'SWAG-CUSTOMER-GROUP-ID-3', 'SWAG-CUSTOMER-GROUP-ID-1']);

        $cart = $this->createMock(Cart::class);

        $group = new CustomerGroupStruct();
        $group->setId('SWAG-CUSTOMER-GROUP-ID-5');

        $context = $this->createMock(CheckoutContext::class);

        $context
            ->method('getCurrentCustomerGroup')
            ->will(static::returnValue($group));

        static::assertFalse(
            $rule->match(new CartRuleScope($cart, $context))->matches()
        );
    }
}
