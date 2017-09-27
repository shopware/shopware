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

namespace Shopware\Cart\Test\Common;

use PHPUnit\Framework\TestCase;
use Shopware\Area\Struct\AreaBasicStruct;
use Shopware\AreaCountry\Struct\AreaCountryBasicStruct;
use Shopware\AreaCountryState\Struct\AreaCountryStateBasicStruct;
use Shopware\Cart\Delivery\DeliveryCalculator;
use Shopware\Cart\Delivery\ShippingLocation;
use Shopware\Cart\Price\PriceDefinition;
use Shopware\Cart\Tax\TaxDetector;
use Shopware\CartBridge\Product\ProductPriceGateway;
use Shopware\Context\Struct\ShopContext;
use Shopware\Currency\Struct\CurrencyBasicStruct;
use Shopware\Customer\Struct\CustomerBasicStruct;
use Shopware\CustomerAddress\Struct\CustomerAddressBasicStruct;
use Shopware\CustomerGroup\Struct\CustomerGroupBasicStruct;
use Shopware\PaymentMethod\Struct\PaymentMethodBasicStruct;
use Shopware\PriceGroupDiscount\Struct\PriceGroupDiscountBasicCollection;
use Shopware\PriceGroupDiscount\Struct\PriceGroupDiscountBasicStruct;
use Shopware\ShippingMethod\Struct\ShippingMethodBasicStruct;
use Shopware\Shop\Struct\ShopDetailStruct;
use Shopware\Tax\Struct\TaxBasicCollection;
use Shopware\Tax\Struct\TaxBasicStruct;

class Generator extends TestCase
{
    public static function createContext(
        $currentCustomerGroup = null,
        $fallbackCustomerGroup = null,
        $shop = null,
        $currency = null,
        $priceGroupDiscounts = null,
        $taxes = null,
        $area = null,
        $country = null,
        $state = null,
        $shipping = null
    ) {
        if (null === $shop) {
            $shop = new ShopDetailStruct();
            $shop->setUuid('SWAG-SHOP-UUID-1');
            $shop->setIsDefault(true);
            $shop->setFallbackLocaleUuid(null);
        }

        $currency = $currency ?: new CurrencyBasicStruct();

        if (!$currentCustomerGroup) {
            $currentCustomerGroup = new CustomerGroupBasicStruct();
            $currentCustomerGroup->setUuid('EK2');
        }

        if (!$fallbackCustomerGroup) {
            $fallbackCustomerGroup = new CustomerGroupBasicStruct();
            $fallbackCustomerGroup->setUuid('EK1');
        }

        if (!$priceGroupDiscounts) {
            $priceGroupDiscount = new PriceGroupDiscountBasicStruct();
            $priceGroupDiscount->setUuid('SWAG-PRICE-GROUP-DISCOUNT-UUID-1');
            $priceGroupDiscounts = new PriceGroupDiscountBasicCollection([$priceGroupDiscount]);
        }

        if (!$taxes) {
            $tax = new TaxBasicStruct();
            $tax->setUuid('SWAG-TAX-UUID-1');
            $tax->setName('test');
            $tax->setRate(19.0);

            $taxes = new TaxBasicCollection([$tax]);
        }

        if (!$area) {
            $area = new AreaBasicStruct();
            $area->setUuid('SWAG-AREA-UUID-1');
        }

        if (!$country) {
            $country = new AreaCountryBasicStruct();
            $country->setUuid('SWAG-AREA-COUNTRY-UUID-1');
            $country->setAreaUuid($area->getUuid());
        }
        if (!$state) {
            $state = new AreaCountryStateBasicStruct();
            $state->setUuid('SWAG-AREA-COUNTRY-STATE-UUID-1');
            $state->setAreaCountryUuid($country->getUuid());
        }

        if (!$shipping) {
            $shipping = new CustomerAddressBasicStruct();
            $shipping->setCountry($country);
            $shipping->setState($state);
        }

        return new ShopContext(
            $shop,
            $currency,
            $currentCustomerGroup,
            $fallbackCustomerGroup,
            $taxes,
            $priceGroupDiscounts,
            new PaymentMethodBasicStruct(1, '', '', ''),
            new ShippingMethodBasicStruct(1, '', DeliveryCalculator::CALCULATION_BY_WEIGHT, true, 1),
            ShippingLocation::createFromAddress($shipping),
            new CustomerBasicStruct()
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
