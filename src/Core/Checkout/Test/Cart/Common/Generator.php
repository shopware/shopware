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

namespace Shopware\Core\Checkout\Test\Cart\Common;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart\Cart;
use Shopware\Core\Checkout\Cart\Delivery\DeliveryCalculator;
use Shopware\Core\Checkout\Cart\Delivery\Struct\ShippingLocation;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\Price;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Cart\Tax\TaxAmountCalculator;
use Shopware\Core\Checkout\Cart\Tax\TaxDetector;
use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressStruct;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupStruct;
use Shopware\Core\Checkout\Customer\CustomerStruct;
use Shopware\Core\Checkout\Payment\PaymentMethodStruct;
use Shopware\Core\Checkout\Shipping\ShippingMethodStruct;
use Shopware\Core\Content\Catalog\CatalogCollection;
use Shopware\Core\Content\Catalog\CatalogStruct;
use Shopware\Core\Content\Product\Cart\ProductGateway;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Core\System\Country\Aggregate\CountryArea\CountryAreaStruct;
use Shopware\Core\System\Country\Aggregate\CountryState\CountryStateStruct;
use Shopware\Core\System\Country\CountryStruct;
use Shopware\Core\System\Currency\CurrencyStruct;
use Shopware\Core\System\Language\LanguageStruct;
use Shopware\Core\System\Locale\LocaleStruct;
use Shopware\Core\System\SalesChannel\SalesChannelStruct;
use Shopware\Core\System\Tax\TaxCollection;
use Shopware\Core\System\Tax\TaxStruct;

class Generator extends TestCase
{
    public static function createContext(
        $currentCustomerGroup = null,
        $fallbackCustomerGroup = null,
        $salesChannel = null,
        $currency = null,
        $priceGroupDiscounts = null,
        $taxes = null,
        $area = null,
        $country = null,
        $state = null,
        $shipping = null,
        $language = null,
        $fallbackLanguage = null,
        $paymentMethod = null
    ) {
        if ($salesChannel === null) {
            $salesChannel = new SalesChannelStruct();
            $salesChannel->setId('ffa32a50e2d04cf38389a53f8d6cd594');
            $salesChannel->setTaxCalculationType(TaxAmountCalculator::CALCULATION_HORIZONTAL);

            $catalogs = new CatalogCollection();
            $catalog = new CatalogStruct();
            $catalog->setName('generated catalog');
            $catalog->setId(Defaults::CATALOG);

            $salesChannel->setCatalogs($catalogs);
        }

        $currency = $currency ?: (new CurrencyStruct())->assign([
            'id' => '4c8eba11-bd35-46d7-86af-bed481a6e665',
            'factor' => 1,
        ]);

        $currency->setFactor(1);

        if (!$currentCustomerGroup) {
            $currentCustomerGroup = new CustomerGroupStruct();
            $currentCustomerGroup->setId(Defaults::FALLBACK_CUSTOMER_GROUP);
            $currentCustomerGroup->setDisplayGross(true);
        }

        if (!$fallbackCustomerGroup) {
            $fallbackCustomerGroup = new CustomerGroupStruct();
            $fallbackCustomerGroup->setId(Defaults::FALLBACK_CUSTOMER_GROUP);
            $currentCustomerGroup->setDisplayGross(true);
        }

        if (!$taxes) {
            $tax = new TaxStruct();
            $tax->setId('49260353-68e3-4d9f-a695-e017d7a231b9');
            $tax->setName('test');
            $tax->setTaxRate(19.0);

            $taxes = new TaxCollection([$tax]);
        }

        if (!$area) {
            $area = new CountryAreaStruct();
            $area->setId('5cff02b1-0297-41a4-891c-430bcd9e3603');
        }

        if (!$country) {
            $country = new CountryStruct();
            $country->setId('5cff02b1-0297-41a4-891c-430bcd9e3603');
            $country->setAreaId($area->getId());
            $country->setTaxFree(false);
            $country->setName('Germany');
        }
        if (!$state) {
            $state = new CountryStateStruct();
            $state->setId('bd5e2dcf-547e-4df6-bb1f-f58a554bc69e');
            $state->setCountryId($country->getId());
        }

        if (!$shipping) {
            $shipping = new CustomerAddressStruct();
            $shipping->setCountry($country);
            $shipping->setCountryState($state);
        }

        if (!$language) {
            $locale = new LocaleStruct();
            $locale->setCode('en_GB');

            $language = new LanguageStruct();
            $language->setId(Defaults::LANGUAGE);
            $language->setLocale($locale);
            $language->setName('Language 1');
        }

        if (!$fallbackLanguage) {
            $locale = new LocaleStruct();
            $locale->setCode('en_GB');

            $fallbackLanguage = new LanguageStruct();
            $fallbackLanguage->setLocale($locale);
            $fallbackLanguage->setName('Fallback Language 1');
        }

        if (!$paymentMethod) {
            $paymentMethod = (new PaymentMethodStruct())->assign(['id' => '19d144ff-e15f-4772-860d-59fca7f207c1']);
        }

        $shippingMethod = new ShippingMethodStruct();
        $shippingMethod->setId('8beeb66e9dda46b18891a059257a590e');
        $shippingMethod->setCalculation(DeliveryCalculator::CALCULATION_BY_PRICE);
        $shippingMethod->setMinDeliveryTime(1);
        $shippingMethod->setMaxDeliveryTime(2);

        $customer = (new CustomerStruct())->assign(['id' => Uuid::uuid4()->getHex()]);
        $customer->setId(Uuid::uuid4()->getHex());
        $customer->setGroup($currentCustomerGroup);

        return new CheckoutContext(
            Defaults::TENANT_ID,
            Uuid::uuid4()->toString(),
            $salesChannel,
            $language,
            $fallbackLanguage,
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
     * @param QuantityPriceDefinition[] $priceDefinitions indexed by product number
     *
     * @return ProductGateway
     */
    public function createProductPriceGateway($priceDefinitions)
    {
        /** @var MockObject|ProductGateway $mock */
        $mock = $this->createMock(ProductGateway::class);
        $mock->expects(static::any())
            ->method('get')
            ->will(static::returnValue($priceDefinitions));

        return $mock;
    }

    public static function createCart(): Cart
    {
        $cart = new Cart('test', 'test');
        $cart->setLineItems(
            new LineItemCollection([
                (new LineItem('A', 'product', 27))
                    ->setPrice(new Price(10, 270, new CalculatedTaxCollection(), new TaxRuleCollection(), 27)),

                (new LineItem('B', 'test', 5))
                    ->setGood(false)
                    ->setPrice(new Price(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection())),
            ])
        );
        $cart->setPrice(
            new CartPrice(275, 275, 0, new CalculatedTaxCollection(), new TaxRuleCollection(), CartPrice::TAX_STATE_GROSS)
        );

        return $cart;
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
