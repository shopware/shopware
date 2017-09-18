<?php declare(strict_types=1);

namespace Shopware\Shop\Struct;

use Shopware\AreaCountry\Struct\AreaCountryBasicCollection;
use Shopware\Category\Struct\CategoryBasicCollection;
use Shopware\Currency\Struct\CurrencyBasicCollection;
use Shopware\CustomerGroup\Struct\CustomerGroupBasicCollection;
use Shopware\Locale\Struct\LocaleBasicCollection;
use Shopware\PaymentMethod\Struct\PaymentMethodBasicCollection;
use Shopware\ShippingMethod\Struct\ShippingMethodBasicCollection;
use Shopware\ShopTemplate\Struct\ShopTemplateBasicCollection;

class ShopDetailCollection extends ShopBasicCollection
{
    /**
     * @var ShopDetailStruct[]
     */
    protected $elements = [];

    public function getFallbackLocales(): LocaleBasicCollection
    {
        return new LocaleBasicCollection(
            $this->fmap(function (ShopDetailStruct $shop) {
                return $shop->getFallbackLocale();
            })
        );
    }

    public function getCategories(): CategoryBasicCollection
    {
        return new CategoryBasicCollection(
            $this->fmap(function (ShopDetailStruct $shop) {
                return $shop->getCategory();
            })
        );
    }

    public function getCustomerGroups(): CustomerGroupBasicCollection
    {
        return new CustomerGroupBasicCollection(
            $this->fmap(function (ShopDetailStruct $shop) {
                return $shop->getCustomerGroup();
            })
        );
    }

    public function getPaymentMethods(): PaymentMethodBasicCollection
    {
        return new PaymentMethodBasicCollection(
            $this->fmap(function (ShopDetailStruct $shop) {
                return $shop->getPaymentMethod();
            })
        );
    }

    public function getShippingMethods(): ShippingMethodBasicCollection
    {
        return new ShippingMethodBasicCollection(
            $this->fmap(function (ShopDetailStruct $shop) {
                return $shop->getShippingMethod();
            })
        );
    }

    public function getCountries(): AreaCountryBasicCollection
    {
        return new AreaCountryBasicCollection(
            $this->fmap(function (ShopDetailStruct $shop) {
                return $shop->getCountry();
            })
        );
    }

    public function getTemplates(): ShopTemplateBasicCollection
    {
        return new ShopTemplateBasicCollection(
            $this->fmap(function (ShopDetailStruct $shop) {
                return $shop->getTemplate();
            })
        );
    }

    public function getAvailableCurrencyUuids(): array
    {
        $uuids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getAvailableCurrencyUuids() as $uuid) {
                $uuids[] = $uuid;
            }
        }

        return $uuids;
    }

    public function getAvailableCurrencies(): CurrencyBasicCollection
    {
        $collection = new CurrencyBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getAvailableCurrencies()->getIterator()->getArrayCopy());
        }

        return $collection;
    }
}
