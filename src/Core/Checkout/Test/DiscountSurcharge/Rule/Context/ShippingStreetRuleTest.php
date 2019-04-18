<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\DiscountSurcharge\Rule\Context;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Delivery\Struct\ShippingLocation;
use Shopware\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\Rule\ShippingStreetRule;
use Shopware\Core\System\Country\Aggregate\CountryState\CountryStateEntity;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class ShippingStreetRuleTest extends TestCase
{
    public function testWithExactMatch(): void
    {
        $rule = (new ShippingStreetRule())->assign(['streetName' => 'example street']);

        $cart = $this->createMock(Cart::class);

        $context = $this->createMock(SalesChannelContext::class);

        $context
            ->method('getShippingLocation')
            ->willReturn(
                ShippingLocation::createFromAddress(
                    $this->createAddress('example street')
                ));

        static::assertTrue(
            $rule->match(new CartRuleScope($cart, $context))
        );
    }

    public function testCaseInsensitive(): void
    {
        $rule = (new ShippingStreetRule())->assign(['streetName' => 'ExaMple StreEt']);

        $cart = $this->createMock(Cart::class);

        $context = $this->createMock(SalesChannelContext::class);

        $context
            ->method('getShippingLocation')
            ->willReturn(
                ShippingLocation::createFromAddress(
                    $this->createAddress('example street')
                ));

        static::assertTrue(
            $rule->match(new CartRuleScope($cart, $context))
        );
    }

    public function testNotMatch(): void
    {
        $rule = (new ShippingStreetRule())->assign(['streetName' => 'example street']);

        $cart = $this->createMock(Cart::class);

        $context = $this->createMock(SalesChannelContext::class);

        $context
            ->method('getShippingLocation')
            ->willReturn(
                ShippingLocation::createFromAddress(
                    $this->createAddress('test street')
                ));

        static::assertFalse(
            $rule->match(new CartRuleScope($cart, $context))
        );
    }

    public function testWithoutAddress(): void
    {
        $rule = (new ShippingStreetRule())->assign(['streetName' => 'ExaMple StreEt']);

        $cart = $this->createMock(Cart::class);

        $context = $this->createMock(SalesChannelContext::class);

        $context
            ->method('getShippingLocation')
            ->willReturn(
                ShippingLocation::createFromCountry(
                    new CountryEntity()
                ));

        static::assertFalse(
            $rule->match(new CartRuleScope($cart, $context))
        );
    }

    private function createAddress(string $street): CustomerAddressEntity
    {
        $address = new CustomerAddressEntity();
        $state = new CountryStateEntity();
        $country = new CountryEntity();
        $state->setCountryId('SWAG-AREA-COUNTRY-ID-1');

        $address->setStreet($street);
        $address->setCountry($country);
        $address->setCountryState($state);

        return $address;
    }
}
