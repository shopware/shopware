<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\DiscountSurcharge\Rule\Context;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart\Cart;
use Shopware\Core\Checkout\Cart\Delivery\Struct\ShippingLocation;
use Shopware\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressStruct;
use Shopware\Core\Checkout\Customer\Rule\ShippingStreetRule;
use Shopware\Core\System\Country\Aggregate\CountryState\CountryStateStruct;
use Shopware\Core\System\Country\CountryStruct;

class ShippingStreetRuleTest extends TestCase
{
    public function testWithExactMatch(): void
    {
        $rule = new ShippingStreetRule('example street');

        $cart = $this->createMock(Cart::class);

        $context = $this->createMock(CheckoutContext::class);

        $context->expects(static::any())
            ->method('getShippingLocation')
            ->will(
                static::returnValue(
                ShippingLocation::createFromAddress(
                    $this->createAddress('example street')
                )
            ));

        static::assertTrue(
            $rule->match(new CartRuleScope($cart, $context))->matches()
        );
    }

    public function testCaseInsensitive(): void
    {
        $rule = new ShippingStreetRule('ExaMple StreEt');

        $cart = $this->createMock(Cart::class);

        $context = $this->createMock(CheckoutContext::class);

        $context->expects(static::any())
            ->method('getShippingLocation')
            ->will(
                static::returnValue(
                ShippingLocation::createFromAddress(
                    $this->createAddress('example street')
                )
            ));

        static::assertTrue(
            $rule->match(new CartRuleScope($cart, $context))->matches()
        );
    }

    public function testNotMatch(): void
    {
        $rule = new ShippingStreetRule('example street');

        $cart = $this->createMock(Cart::class);

        $context = $this->createMock(CheckoutContext::class);

        $context->expects(static::any())
            ->method('getShippingLocation')
            ->will(
                static::returnValue(
                ShippingLocation::createFromAddress(
                    $this->createAddress('test street')
                )
            ));

        static::assertFalse(
            $rule->match(new CartRuleScope($cart, $context))->matches()
        );
    }

    public function testWithoutAddress(): void
    {
        $rule = new ShippingStreetRule('ExaMple StreEt');

        $cart = $this->createMock(Cart::class);

        $context = $this->createMock(CheckoutContext::class);

        $context->expects(static::any())
            ->method('getShippingLocation')
            ->will(
                static::returnValue(
                ShippingLocation::createFromCountry(
                    new CountryStruct()
                )
            ));

        static::assertFalse(
            $rule->match(new CartRuleScope($cart, $context))->matches()
        );
    }

    private function createAddress(string $street): CustomerAddressStruct
    {
        $address = new CustomerAddressStruct();
        $state = new CountryStateStruct();
        $country = new CountryStruct();
        $state->setCountryId('SWAG-AREA-COUNTRY-ID-1');

        $address->setStreet($street);
        $address->setCountry($country);
        $address->setCountryState($state);

        return $address;
    }
}
