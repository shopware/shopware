<?php declare(strict_types=1);
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
use Shopware\Api\Country\Struct\CountryAreaBasicStruct;
use Shopware\Api\Country\Struct\CountryBasicStruct;
use Shopware\Api\Country\Struct\CountryStateBasicStruct;
use Shopware\Api\Currency\Struct\CurrencyBasicStruct;
use Shopware\Api\Customer\Struct\CustomerAddressBasicStruct;
use Shopware\Api\Customer\Struct\CustomerBasicStruct;
use Shopware\Api\Customer\Struct\CustomerGroupBasicStruct;
use Shopware\Api\Payment\Struct\PaymentMethodBasicStruct;
use Shopware\Api\Shipping\Struct\ShippingMethodBasicStruct;
use Shopware\Api\Shop\Struct\ShopDetailStruct;
use Shopware\Api\Tax\Collection\TaxBasicCollection;
use Shopware\Api\Tax\Struct\TaxBasicStruct;
use Shopware\Cart\Delivery\Struct\ShippingLocation;
use Shopware\Cart\Tax\TaxDetector;
use Shopware\CartBridge\Product\ProductPriceGateway;
use Shopware\Context\Struct\ShopContext;

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
        if ($shop === null) {
            $shop = new ShopDetailStruct();
            $shop->setUuid('SWAG-SHOP-UUID-1');
            $shop->setIsDefault(true);
            $shop->setFallbackTranslationUuid(null);
        }

        $currency = $currency ?: (new CurrencyBasicStruct())->assign([
            'uuid' => '1',
        ]);

        if (!$currentCustomerGroup) {
            $currentCustomerGroup = new CustomerGroupBasicStruct();
            $currentCustomerGroup->setUuid('EK2');
        }

        if (!$fallbackCustomerGroup) {
            $fallbackCustomerGroup = new CustomerGroupBasicStruct();
            $fallbackCustomerGroup->setUuid('EK1');
        }

        if (!$taxes) {
            $tax = new TaxBasicStruct();
            $tax->setUuid('SWAG-TAX-UUID-1');
            $tax->setName('test');
            $tax->setRate(19.0);

            $taxes = new TaxBasicCollection([$tax]);
        }

        if (!$area) {
            $area = new CountryAreaBasicStruct();
            $area->setUuid('SWAG-AREA-UUID-1');
        }

        if (!$country) {
            $country = new CountryBasicStruct();
            $country->setUuid('SWAG-AREA-COUNTRY-UUID-1');
            $country->setAreaUuid($area->getUuid());
        }
        if (!$state) {
            $state = new CountryStateBasicStruct();
            $state->setUuid('SWAG-AREA-COUNTRY-STATE-UUID-1');
            $state->setCountryUuid($country->getUuid());
        }

        if (!$shipping) {
            $shipping = new CustomerAddressBasicStruct();
            $shipping->setCountry($country);
            $shipping->setCountryState($state);
        }

        $paymentMethod = (new PaymentMethodBasicStruct())->assign(['uuid' => '1']);
        $shippingMethod = (new ShippingMethodBasicStruct())->assign(['uuid' => '1']);
        $customer = (new CustomerBasicStruct())->assign(['uuid' => '1']);

        return new ShopContext(
            $shop,
            $currency,
            $currentCustomerGroup,
            $fallbackCustomerGroup,
            $taxes,
            $paymentMethod,
            $shippingMethod,
            ShippingLocation::createFromAddress($shipping),
            $customer
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
     * @param \Shopware\Cart\Price\Struct\PriceDefinition[] $priceDefinitions indexed by product number
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
