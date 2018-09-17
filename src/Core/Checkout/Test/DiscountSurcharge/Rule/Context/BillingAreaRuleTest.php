<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\DiscountSurcharge\Rule\Context;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart\Cart;
use Shopware\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressStruct;
use Shopware\Core\Checkout\Customer\CustomerStruct;
use Shopware\Core\Checkout\Customer\Rule\BillingAreaRule;
use Shopware\Core\System\Country\CountryStruct;

class BillingAreaRuleTest extends TestCase
{
    public function testWithExactMatch(): void
    {
        $rule = new BillingAreaRule(['SWAG-AREA-ID-1']);

        $cart = $this->createMock(Cart::class);

        $context = $this->createMock(CheckoutContext::class);

        $country = new CountryStruct();
        $country->setId('SWAG-AREA-COUNTRY-ID-1');
        $country->setAreaId('SWAG-AREA-ID-1');

        $billing = new CustomerAddressStruct();
        $billing->setCountry($country);

        $customer = new CustomerStruct();
        $customer->setDefaultBillingAddress($billing);

        $context->expects(static::any())
            ->method('getCustomer')
            ->will(static::returnValue($customer));

        static::assertTrue(
            $rule->match(new CartRuleScope($cart, $context))->matches()
        );
    }

    public function testWithNotMatch(): void
    {
        $rule = new BillingAreaRule(['SWAG-AREA-ID-2']);

        $cart = $this->createMock(Cart::class);

        $context = $this->createMock(CheckoutContext::class);

        $country = new CountryStruct();
        $country->setId('SWAG-AREA-COUNTRY-ID-1');
        $country->setAreaId('SWAG-AREA-ID-1');

        $billing = new CustomerAddressStruct();
        $billing->setCountry($country);

        $customer = new CustomerStruct();
        $customer->setDefaultBillingAddress($billing);

        $context->expects(static::any())
            ->method('getCustomer')
            ->will(static::returnValue($customer));

        static::assertFalse(
            $rule->match(new CartRuleScope($cart, $context))->matches()
        );
    }

    public function testMultipleCountries(): void
    {
        $rule = new BillingAreaRule(['SWAG-AREA-ID-1', 'SWAG-AREA-ID-3', 'SWAG-AREA-ID-2']);

        $cart = $this->createMock(Cart::class);

        $context = $this->createMock(CheckoutContext::class);

        $country = new CountryStruct();
        $country->setId('SWAG-AREA-COUNTRY-ID-1');
        $country->setAreaId('SWAG-AREA-ID-3');

        $billing = new CustomerAddressStruct();
        $billing->setCountry($country);

        $customer = new CustomerStruct();
        $customer->setDefaultBillingAddress($billing);

        $context->expects(static::any())
            ->method('getCustomer')
            ->will(static::returnValue($customer));

        static::assertTrue(
            $rule->match(new CartRuleScope($cart, $context))->matches()
        );
    }

    public function testWithoutCustomer(): void
    {
        $rule = new BillingAreaRule(['SWAG-AREA-ID-1', 'SWAG-AREA-ID-3', 'SWAG-AREA-ID-2']);

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
