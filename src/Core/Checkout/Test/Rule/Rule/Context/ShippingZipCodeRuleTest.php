<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Rule\Rule\Context;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Delivery\Struct\ShippingLocation;
use Shopware\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\Rule\ShippingZipCodeRule;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 */
#[Package('business-ops')]
class ShippingZipCodeRuleTest extends TestCase
{
    public function testEqualsWithSingleCode(): void
    {
        $rule = (new ShippingZipCodeRule())->assign(['zipCodes' => ['ABC123']]);
        $address = $this->createAddress('ABC123');

        $cart = new Cart('test');

        $context = $this->createMock(SalesChannelContext::class);

        $location = ShippingLocation::createFromAddress($address);

        $context
            ->method('getShippingLocation')
            ->willReturn($location);

        static::assertTrue(
            $rule->match(new CartRuleScope($cart, $context))
        );
    }

    public function testEqualsWithMultipleCodes(): void
    {
        $rule = (new ShippingZipCodeRule())->assign(['zipCodes' => ['ABC1', 'ABC2', 'ABC3']]);
        $address = $this->createAddress('ABC2');

        $cart = new Cart('test');

        $context = $this->createMock(SalesChannelContext::class);

        $location = ShippingLocation::createFromAddress($address);

        $context
            ->method('getShippingLocation')
            ->willReturn($location);

        static::assertTrue(
            $rule->match(new CartRuleScope($cart, $context))
        );
    }

    public function testNotMatchWithSingleCode(): void
    {
        $rule = (new ShippingZipCodeRule())->assign(['zipCodes' => ['ABC1', 'ABC2', 'ABC3']]);
        $address = $this->createAddress('ABC4');

        $cart = new Cart('test');

        $context = $this->createMock(SalesChannelContext::class);

        $location = ShippingLocation::createFromAddress($address);

        $context
            ->method('getShippingLocation')
            ->willReturn($location);

        static::assertFalse(
            $rule->match(new CartRuleScope($cart, $context))
        );
    }

    public function testWithoutShippingAddress(): void
    {
        $rule = (new ShippingZipCodeRule())->assign(['zipCodes' => ['ABC1', 'ABC2', 'ABC3']]);

        $cart = new Cart('test');

        $context = $this->createMock(SalesChannelContext::class);

        $location = ShippingLocation::createFromCountry(new CountryEntity());

        $context
            ->method('getShippingLocation')
            ->willReturn($location);

        static::assertFalse(
            $rule->match(new CartRuleScope($cart, $context))
        );
    }

    private function createAddress(string $code): CustomerAddressEntity
    {
        $address = new CustomerAddressEntity();
        $address->setZipcode($code);
        $address->setCountry(new CountryEntity());

        return $address;
    }
}
