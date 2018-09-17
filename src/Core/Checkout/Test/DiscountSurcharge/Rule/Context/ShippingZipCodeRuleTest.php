<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\DiscountSurcharge\Rule\Context;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart\Cart;
use Shopware\Core\Checkout\Cart\Delivery\Struct\ShippingLocation;
use Shopware\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressStruct;
use Shopware\Core\Checkout\Customer\Rule\ShippingZipCodeRule;
use Shopware\Core\System\Country\CountryStruct;

class ShippingZipCodeRuleTest extends TestCase
{
    public function testEqualsWithSingleCode(): void
    {
        $rule = new ShippingZipCodeRule(['ABC123']);
        $address = $this->createAddress('ABC123');

        $cart = $this->createMock(Cart::class);

        $context = $this->createMock(CheckoutContext::class);

        $location = ShippingLocation::createFromAddress($address);

        $context->expects(static::any())
            ->method('getShippingLocation')
            ->will(static::returnValue($location));

        static::assertTrue(
            $rule->match(new CartRuleScope($cart, $context))->matches()
        );
    }

    public function testEqualsWithMultipleCodes(): void
    {
        $rule = new ShippingZipCodeRule(['ABC1', 'ABC2', 'ABC3']);
        $address = $this->createAddress('ABC2');

        $cart = $this->createMock(Cart::class);

        $context = $this->createMock(CheckoutContext::class);

        $location = ShippingLocation::createFromAddress($address);

        $context->expects(static::any())
            ->method('getShippingLocation')
            ->will(static::returnValue($location));

        static::assertTrue(
            $rule->match(new CartRuleScope($cart, $context))->matches()
        );
    }

    public function testNotMatchWithSingleCode(): void
    {
        $rule = new ShippingZipCodeRule(['ABC1', 'ABC2', 'ABC3']);
        $address = $this->createAddress('ABC4');

        $cart = $this->createMock(Cart::class);

        $context = $this->createMock(CheckoutContext::class);

        $location = ShippingLocation::createFromAddress($address);

        $context->expects(static::any())
            ->method('getShippingLocation')
            ->will(static::returnValue($location));

        static::assertFalse(
            $rule->match(new CartRuleScope($cart, $context))->matches()
        );
    }

    public function testWithoutShippingAddress(): void
    {
        $rule = new ShippingZipCodeRule(['ABC1', 'ABC2', 'ABC3']);

        $cart = $this->createMock(Cart::class);

        $context = $this->createMock(CheckoutContext::class);

        $location = ShippingLocation::createFromCountry(new CountryStruct());

        $context->expects(static::any())
            ->method('getShippingLocation')
            ->will(static::returnValue($location));

        static::assertFalse(
            $rule->match(new CartRuleScope($cart, $context))->matches()
        );
    }

    private function createAddress(string $code): CustomerAddressStruct
    {
        $address = new CustomerAddressStruct();
        $address->setZipcode($code);
        $address->setCountry(new CountryStruct());

        return $address;
    }
}
