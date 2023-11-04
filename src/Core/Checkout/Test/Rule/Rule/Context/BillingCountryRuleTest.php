<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Rule\Rule\Context;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Rule\BillingCountryRule;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 */
#[Package('business-ops')]
class BillingCountryRuleTest extends TestCase
{
    public function testWithExactMatch(): void
    {
        $rule = (new BillingCountryRule())->assign(['countryIds' => ['SWAG-AREA-COUNTRY-ID-1']]);

        $cart = new Cart('test');

        $context = $this->createMock(SalesChannelContext::class);

        $country = new CountryEntity();
        $country->setId('SWAG-AREA-COUNTRY-ID-1');

        $billing = new CustomerAddressEntity();
        $billing->setCountry($country);

        $customer = new CustomerEntity();
        $customer->setDefaultBillingAddress($billing);

        $context
            ->method('getCustomer')
            ->willReturn($customer);

        static::assertTrue(
            $rule->match(new CartRuleScope($cart, $context))
        );
    }

    public function testWithNotMatch(): void
    {
        $rule = (new BillingCountryRule())->assign(['countryIds' => ['SWAG-AREA-COUNTRY-ID-2']]);

        $cart = new Cart('test');

        $context = $this->createMock(SalesChannelContext::class);

        $country = new CountryEntity();
        $country->setId('SWAG-AREA-COUNTRY-ID-1');

        $billing = new CustomerAddressEntity();
        $billing->setCountry($country);

        $customer = new CustomerEntity();
        $customer->setDefaultBillingAddress($billing);

        $context
            ->method('getCustomer')
            ->willReturn($customer);

        static::assertFalse(
            $rule->match(new CartRuleScope($cart, $context))
        );
    }

    public function testMultipleCountries(): void
    {
        $rule = (new BillingCountryRule())->assign(['countryIds' => ['SWAG-AREA-COUNTRY-ID-1', 'SWAG-AREA-COUNTRY-ID-3', 'SWAG-AREA-COUNTRY-ID-2']]);

        $cart = new Cart('test');

        $context = $this->createMock(SalesChannelContext::class);

        $country = new CountryEntity();
        $country->setId('SWAG-AREA-COUNTRY-ID-1');

        $billing = new CustomerAddressEntity();
        $billing->setCountry($country);

        $customer = new CustomerEntity();
        $customer->setDefaultBillingAddress($billing);

        $context
            ->method('getCustomer')
            ->willReturn($customer);

        static::assertTrue(
            $rule->match(new CartRuleScope($cart, $context))
        );
    }

    public function testWithoutCustomer(): void
    {
        $rule = (new BillingCountryRule())->assign(['countryIds' => ['SWAG-AREA-COUNTRY-ID-1', 'SWAG-AREA-COUNTRY-ID-3', 'SWAG-AREA-COUNTRY-ID-2']]);

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
