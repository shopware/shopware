<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Rule\Rule\Context;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Delivery\Struct\ShippingLocation;
use Shopware\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\CustomerException;
use Shopware\Core\Checkout\Customer\Rule\ShippingHouseNumberRule;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Exception\UnsupportedValueException;
use Shopware\Core\System\Country\Aggregate\CountryState\CountryStateEntity;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
#[CoversClass(ShippingHouseNumberRule::class)]
class ShippingHouseNumberRuleTest extends TestCase
{
    public function testWithExactMatch(): void
    {
        $rule = (new ShippingHouseNumberRule())->assign(['houseNumber' => '123']);

        $cart = new Cart('test');

        $context = $this->createMock(SalesChannelContext::class);

        $context
            ->method('getShippingLocation')
            ->willReturn(
                ShippingLocation::createFromAddress(
                    $this->createAddress('123')
                )
            );

        static::assertTrue(
            $rule->match(new CartRuleScope($cart, $context))
        );
    }

    public function testCaseInsensitive(): void
    {
        $rule = (new ShippingHouseNumberRule())->assign(['houseNumber' => '123a']);

        $cart = new Cart('test');

        $context = $this->createMock(SalesChannelContext::class);

        $context
            ->method('getShippingLocation')
            ->willReturn(
                ShippingLocation::createFromAddress(
                    $this->createAddress('123A')
                )
            );

        static::assertTrue(
            $rule->match(new CartRuleScope($cart, $context))
        );
    }

    public function testNotMatch(): void
    {
        $rule = (new ShippingHouseNumberRule())->assign(['houseNumber' => '123']);

        $cart = new Cart('test');

        $context = $this->createMock(SalesChannelContext::class);

        $context
            ->method('getShippingLocation')
            ->willReturn(
                ShippingLocation::createFromAddress(
                    $this->createAddress('000')
                )
            );

        static::assertFalse(
            $rule->match(new CartRuleScope($cart, $context))
        );
    }

    public function testWithoutAddress(): void
    {
        $rule = (new ShippingHouseNumberRule())->assign(['houseNumber' => '123']);

        $cart = new Cart('test');

        $context = $this->createMock(SalesChannelContext::class);

        $context
            ->method('getShippingLocation')
            ->willReturn(
                ShippingLocation::createFromCountry(
                    new CountryEntity()
                )
            );

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
            ->method('getShippingLocation')
            ->willReturn(
                ShippingLocation::createFromAddress(
                    $this->createAddress('000')
                )
            );

        (new ShippingHouseNumberRule())->match(
            new CartRuleScope(
                new Cart('test'),
                $context
            )
        );
    }

    private function createAddress(string $houseNumber): CustomerAddressEntity
    {
        $address = new CustomerAddressEntity();
        $state = new CountryStateEntity();
        $country = new CountryEntity();
        $state->setCountryId('SWAG-AREA-COUNTRY-ID-1');

        $address->setHouseNumber($houseNumber);
        $address->setCountry($country);
        $address->setCountryState($state);

        return $address;
    }
}
