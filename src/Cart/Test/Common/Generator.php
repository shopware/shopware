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
use Shopware\Context\Struct\StorefrontContext;
use Shopware\Defaults;
use Shopware\Framework\Struct\Uuid;

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
            $shop->setId('FFA32A50-E2D0-4CF3-8389-A53F8D6CD594');
            $shop->setIsDefault(true);
            $shop->setFallbackTranslationId(null);
        }

        $currency = $currency ?: (new CurrencyBasicStruct())->assign([
            'id' => '4c8eba11-bd35-46d7-86af-bed481a6e665',
        ]);

        if (!$currentCustomerGroup) {
            $currentCustomerGroup = new CustomerGroupBasicStruct();
            $currentCustomerGroup->setId(Defaults::FALLBACK_CUSTOMER_GROUP);
        }

        if (!$fallbackCustomerGroup) {
            $fallbackCustomerGroup = new CustomerGroupBasicStruct();
            $fallbackCustomerGroup->setId(Defaults::FALLBACK_CUSTOMER_GROUP);
        }

        if (!$taxes) {
            $tax = new TaxBasicStruct();
            $tax->setId('49260353-68e3-4d9f-a695-e017d7a231b9');
            $tax->setName('test');
            $tax->setRate(19.0);

            $taxes = new TaxBasicCollection([$tax]);
        }

        if (!$area) {
            $area = new CountryAreaBasicStruct();
            $area->setId('5cff02b1-0297-41a4-891c-430bcd9e3603');
        }

        if (!$country) {
            $country = new CountryBasicStruct();
            $country->setId('5cff02b1-0297-41a4-891c-430bcd9e3603');
            $country->setAreaId($area->getId());
        }
        if (!$state) {
            $state = new CountryStateBasicStruct();
            $state->setId('bd5e2dcf-547e-4df6-bb1f-f58a554bc69e');
            $state->setCountryId($country->getId());
        }

        if (!$shipping) {
            $shipping = new CustomerAddressBasicStruct();
            $shipping->setCountry($country);
            $shipping->setCountryState($state);
        }

        $paymentMethod = (new PaymentMethodBasicStruct())->assign(['id' => '19d144ff-e15f-4772-860d-59fca7f207c1']);
        $shippingMethod = (new ShippingMethodBasicStruct())->assign(['id' => '8beeb66e-9dda-46b1-8891-a059257a590e']);
        $customer = (new CustomerBasicStruct())->assign(['id' => Uuid::uuid4()->getHex()]);

        return new StorefrontContext(
            Uuid::uuid4()->toString(),
            $shop,
            $currency,
            $currentCustomerGroup,
            $fallbackCustomerGroup,
            $taxes,
            $paymentMethod,
            $shippingMethod,
            ShippingLocation::createFromAddress($shipping),
            $customer,
            []
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
