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
use Shopware\Cart\Delivery\DeliveryCalculator;
use Shopware\Cart\Delivery\Struct\ShippingLocation;
use Shopware\Cart\Tax\TaxAmountCalculator;
use Shopware\Cart\Tax\TaxDetector;
use Shopware\Context\Struct\StorefrontContext;
use Shopware\Defaults;
use Shopware\Framework\Struct\Uuid;

class Generator extends TestCase
{
    public static function createContext(
        ?CustomerGroupBasicStruct $currentCustomerGroup = null,
        ?CustomerGroupBasicStruct $fallbackCustomerGroup = null,
        ?ShopDetailStruct $shop = null,
        ?CurrencyBasicStruct $currency = null,
        $priceGroupDiscounts = null,
        ?TaxBasicCollection $taxes = null,
        ?string $areaId = null,
        ?CountryBasicStruct $country = null,
        ?CountryStateBasicStruct $state = null,
        ?CustomerAddressBasicStruct $shipping = null,
        array $contextRuleIds = []
    ) {
        if ($shop === null) {
            $shop = new ShopDetailStruct();
            $shop->setId('FFA32A50E2D04CF38389A53F8D6CD594');
            $shop->setIsDefault(true);
            $shop->setFallbackTranslationId(null);
            $shop->setTaxCalculationType(TaxAmountCalculator::CALCULATION_HORIZONTAL);
            $shop->setCatalogIds(['FFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFF']);
        }

        $currency = $currency ?: (new CurrencyBasicStruct())->assign([
            'id' => '4c8eba11bd3546d786afbed481a6e665',
            'factor' => 1,
        ]);

        if (!$currentCustomerGroup) {
            $currentCustomerGroup = new CustomerGroupBasicStruct();
            $currentCustomerGroup->setId(Defaults::FALLBACK_CUSTOMER_GROUP);
            $currentCustomerGroup->setDisplayGross(true);
        }

        if (!$fallbackCustomerGroup) {
            $fallbackCustomerGroup = new CustomerGroupBasicStruct();
            $fallbackCustomerGroup->setId(Defaults::FALLBACK_CUSTOMER_GROUP);
        }

        if (!$taxes) {
            $tax = new TaxBasicStruct();
            $tax->setId('4926035368e34d9fa695e017d7a231b9');
            $tax->setName('test');
            $tax->setRate(19.0);

            $taxes = new TaxBasicCollection([$tax]);
        }
        $area = new CountryAreaBasicStruct();
        if (!$areaId) {
            $area->setId('5cff02b1029741a4891c430bcd9e3603');
        } else {
            $area->setId($areaId);
        }

        if (!$country) {
            $country = new CountryBasicStruct();
            $country->setId('5cff02b1029741a4891c430bcd9e3603');
            $country->setAreaId($area->getId());
            $country->setTaxFree(false);
            $country->setName('Germany');
        }
        if (!$state) {
            $state = new CountryStateBasicStruct();
            $state->setId('bd5e2dcf547e4df6bb1ff58a554bc69e');
            $state->setCountryId($country->getId());
        }

        if (!$shipping) {
            $shipping = new CustomerAddressBasicStruct();
            $shipping->setCountry($country);
            $shipping->setCountryState($state);
        }

        $paymentMethod = (new PaymentMethodBasicStruct())->assign(['id' => '19d144ffe15f4772860d59fca7f207c1']);
        $shippingMethod = new ShippingMethodBasicStruct();
        $shippingMethod->setId('8beeb66e9dda46b18891a059257a590e');
        $shippingMethod->setCalculation(DeliveryCalculator::CALCULATION_BY_PRICE);
        $shippingMethod->setMinDeliveryTime(1);
        $shippingMethod->setMaxDeliveryTime(2);

        $customer = new CustomerBasicStruct();
        $customer->setId(Uuid::uuid4()->getHex());
        $customer->setGroup($currentCustomerGroup);

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
            $contextRuleIds
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
