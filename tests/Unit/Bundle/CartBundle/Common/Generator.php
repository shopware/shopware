<?php
/**
 * Shopware 5
 * Copyright (c) shopware AG
 *
 * According to our dual licensing model, this program can be used either
 * under the terms of the GNU Affero General Public License, version 3,
 * or under a proprietary license.
 *
 * The texts of the GNU Affero General Public License with an additional
 * permission and of our proprietary license can be found at and
 * in the LICENSE file you have received along with this program.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * "Shopware" is a registered trademark of shopware AG.
 * The licensing of the program under the AGPLv3 does not imply a
 * trademark license. Therefore any rights, title and interest in
 * our trademarks remain entirely with us.
 */

namespace Shopware\Tests\Unit\Bundle\CartBundle\Common;

use PHPUnit\Framework\TestCase;
use Shopware\Cart\Delivery\ShippingLocation;
use Shopware\Cart\Price\PriceDefinition;
use Shopware\Cart\Tax\TaxDetector;
use Shopware\CartBridge\Product\ProductPriceGateway;
use Shopware\Address\Struct\Address;
use Shopware\Context\Struct\ShopContext;
use Shopware\CountryArea\Struct\CountryArea;
use Shopware\Country\Struct\Country;
use Shopware\CountryState\Struct\CountryState;
use Shopware\Currency\Struct\Currency;
use Shopware\Customer\Struct\Customer;
use Shopware\CustomerGroup\Struct\CustomerGroup;
use Shopware\PaymentMethod\Struct\PaymentMethod;
use Shopware\PriceGroup\Struct\PriceGroup;
use Shopware\ShippingMethod\Struct\ShippingMethod;
use Shopware\Shop\Struct\Shop;
use Shopware\Tax\Struct\Tax;

class Generator extends TestCase
{
    public static function createContext(
        $currentCustomerGroup = null,
        $fallbackCustomerGroup = null,
        $shop = null,
        $currency = null,
        $priceGroups = null,
        $taxes = null,
        $area = null,
        $country = null,
        $state = null,
        $shipping = null
    ) {
        if ($shop === null) {
            $shop = new Shop();
            $shop->setId(1);
            $shop->setIsDefault(true);
            $shop->setFallbackId(null);
        }

        $currency = $currency ?: new Currency();

        if (!$currentCustomerGroup) {
            $currentCustomerGroup = new CustomerGroup();
            $currentCustomerGroup->setKey('EK2');
        }

        if (!$fallbackCustomerGroup) {
            $fallbackCustomerGroup = new CustomerGroup();
            $fallbackCustomerGroup->setKey('EK1');
        }

        $priceGroups = $priceGroups ?: [new PriceGroup()];
        $taxes = $taxes ?: [new Tax(1, 'test', 19.0)];

        $area = $area ?: new CountryArea();

        if (!$country) {
            $country = new Country();
            $country->setArea($area);
        }
        if (!$state) {
            $state = new CountryState();
            $state->setCountry($country);
        }

        if (!$shipping) {
            $shipping = new Address();
            $shipping->setCountry($country);
            $shipping->setState($state);
        }

        return new ShopContext(
            $shop,
            $currency,
            $currentCustomerGroup,
            $fallbackCustomerGroup,
            $taxes,
            $priceGroups,
            new PaymentMethod(1, '', '', ''),
            new ShippingMethod(1, '', ShippingMethod::CALCULATION_BY_WEIGHT, true, 1),
            ShippingLocation::createFromAddress($shipping),
            new Customer()
        );
    }

    public static function createGrossPriceDetector()
    {
        $self = new self();

        return $self->createTaxDetector(true, false);
    }

    public static function createNetPriceDetector()
    {
        $self = new self();

        return $self->createTaxDetector(false, false);
    }

    public static function createNetDeliveryDetector()
    {
        $self = new self();

        return $self->createTaxDetector(false, true);
    }

    /**
     * @param PriceDefinition[] $priceDefinitions indexed by product number
     *
     * @return \PHPUnit_Framework_MockObject_MockObject|ProductPriceGateway
     */
    public function createProductPriceGateway($priceDefinitions)
    {
        $mock = $this->createMock(ProductPriceGateway::class);
        $mock->expects(static::any())
            ->method('get')
            ->will(static::returnValue($priceDefinitions));

        return $mock;
    }

    private function createTaxDetector($useGross, $isNetDelivery)
    {
        $mock = $this->createMock(TaxDetector::class);
        $mock->expects(static::any())
            ->method('useGross')
            ->will(static::returnValue($useGross));

        $mock->expects(static::any())
            ->method('isNetDelivery')
            ->will(static::returnValue($isNetDelivery));

        return $mock;
    }
}
