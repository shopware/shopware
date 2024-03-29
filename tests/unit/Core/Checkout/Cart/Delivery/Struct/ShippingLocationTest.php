<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Delivery\Struct;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Delivery\Struct\ShippingLocation;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Country\Aggregate\CountryState\CountryStateEntity;
use Shopware\Core\System\Country\CountryEntity;

/**
 * @internal
 */
#[CoversClass(ShippingLocation::class)]
#[Package('checkout')]
class ShippingLocationTest extends TestCase
{
    public function testCreateFromAddress(): void
    {
        $country = new CountryEntity();
        $country->assign([
            'name' => 'test-country-name',
        ]);

        $state = new CountryStateEntity();
        $state->assign([
            'name' => 'test-state-name',
        ]);

        $address = new CustomerAddressEntity();
        $address->assign([
            'id' => 'test-id',
            'country' => $country,
            'countryState' => $state,
        ]);

        $shippingLocation = ShippingLocation::createFromAddress($address);

        static::assertSame('test-country-name', $shippingLocation->getCountry()->getName());
        static::assertSame('test-state-name', $shippingLocation->getState()?->getName());
        static::assertSame('test-id', $shippingLocation->getAddress()?->getId());
    }

    public function testCreateFromCountry(): void
    {
        $country = new CountryEntity();
        $country->assign([
            'name' => 'test-country-name',
        ]);

        $shippingLocation = ShippingLocation::createFromCountry($country);

        static::assertSame('test-country-name', $shippingLocation->getCountry()->getName());
        static::assertNull($shippingLocation->getState());
        static::assertNull($shippingLocation->getAddress());
    }

    public function testApi(): void
    {
        $country = new CountryEntity();
        $country->assign([
            'name' => 'test-country-name',
        ]);

        $state = new CountryStateEntity();
        $state->assign([
            'name' => 'test-state-name',
        ]);

        $shippingLocation = new ShippingLocation($country, $state, null);

        static::assertSame('test-country-name', $shippingLocation->getCountry()->getName());
        static::assertSame('test-state-name', $shippingLocation->getState()?->getName());
    }

    public function testApiWithAddress(): void
    {
        $country = new CountryEntity();
        $country->assign([
            'name' => 'test-country-name',
        ]);

        $state = new CountryStateEntity();
        $state->assign([
            'name' => 'test-state-name',
        ]);

        $address = new CustomerAddressEntity();
        $address->assign([
            'id' => 'test-id',
            'country' => $country,
            'countryState' => $state,
        ]);

        $shippingLocation = new ShippingLocation($country, $state, $address);

        static::assertSame('test-country-name', $shippingLocation->getCountry()->getName());
        static::assertSame('test-state-name', $shippingLocation->getState()?->getName());
        static::assertSame('test-id', $shippingLocation->getAddress()?->getId());
    }

    public function testGetApiAlias(): void
    {
        $shippingLocation = new ShippingLocation(new CountryEntity(), null, null);

        static::assertSame('cart_delivery_shipping_location', $shippingLocation->getApiAlias());
    }
}
