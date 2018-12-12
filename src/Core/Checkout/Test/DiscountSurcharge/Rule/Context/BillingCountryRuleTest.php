<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\DiscountSurcharge\Rule\Context;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart\Cart;
use Shopware\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Rule\BillingCountryRule;
use Shopware\Core\System\Country\CountryEntity;

class BillingCountryRuleTest extends TestCase
{
    public function testWithExactMatch(): void
    {
        $rule = (new BillingCountryRule())->assign(['countryIds' => ['SWAG-AREA-COUNTRY-ID-1']]);

        $cart = $this->createMock(Cart::class);

        $context = $this->createMock(CheckoutContext::class);

        $country = new CountryEntity();
        $country->setId('SWAG-AREA-COUNTRY-ID-1');

        $billing = new CustomerAddressEntity();
        $billing->setCountry($country);

        $customer = new CustomerEntity();
        $customer->setDefaultBillingAddress($billing);

        $context
            ->method('getCustomer')
            ->will(static::returnValue($customer));

        static::assertTrue(
            $rule->match(new CartRuleScope($cart, $context))->matches()
        );
    }

    public function testWithNotMatch(): void
    {
        $rule = (new BillingCountryRule())->assign(['countryIds' => ['SWAG-AREA-COUNTRY-ID-2']]);

        $cart = $this->createMock(Cart::class);

        $context = $this->createMock(CheckoutContext::class);

        $country = new CountryEntity();
        $country->setId('SWAG-AREA-COUNTRY-ID-1');

        $billing = new CustomerAddressEntity();
        $billing->setCountry($country);

        $customer = new CustomerEntity();
        $customer->setDefaultBillingAddress($billing);

        $context
            ->method('getCustomer')
            ->will(static::returnValue($customer));

        static::assertFalse(
            $rule->match(new CartRuleScope($cart, $context))->matches()
        );
    }

    public function testMultipleCountries(): void
    {
        $rule = (new BillingCountryRule())->assign(['countryIds' => ['SWAG-AREA-COUNTRY-ID-1', 'SWAG-AREA-COUNTRY-ID-3', 'SWAG-AREA-COUNTRY-ID-2']]);

        $cart = $this->createMock(Cart::class);

        $context = $this->createMock(CheckoutContext::class);

        $country = new CountryEntity();
        $country->setId('SWAG-AREA-COUNTRY-ID-1');

        $billing = new CustomerAddressEntity();
        $billing->setCountry($country);

        $customer = new CustomerEntity();
        $customer->setDefaultBillingAddress($billing);

        $context
            ->method('getCustomer')
            ->will(static::returnValue($customer));

        static::assertTrue(
            $rule->match(new CartRuleScope($cart, $context))->matches()
        );
    }

    public function testWithoutCustomer(): void
    {
        $rule = (new BillingCountryRule())->assign(['countryIds' => ['SWAG-AREA-COUNTRY-ID-1', 'SWAG-AREA-COUNTRY-ID-3', 'SWAG-AREA-COUNTRY-ID-2']]);

        $cart = $this->createMock(Cart::class);

        $context = $this->createMock(CheckoutContext::class);

        $context
            ->method('getCustomer')
            ->will(static::returnValue(null));

        static::assertFalse(
            $rule->match(new CartRuleScope($cart, $context))->matches()
        );
    }
}
