<?php declare(strict_types=1);

namespace Shopware\Api\Shop\Collection;

use Shopware\Content\Category\Collection\CategoryBasicCollection;
use Shopware\System\Country\Collection\CountryBasicCollection;
use Shopware\System\Currency\Collection\CurrencyBasicCollection;
use Shopware\Checkout\Customer\Collection\CustomerGroupBasicCollection;
use Shopware\Checkout\Payment\Collection\PaymentMethodBasicCollection;
use Shopware\Checkout\Shipping\Collection\ShippingMethodBasicCollection;
use Shopware\Api\Shop\Struct\ShopDetailStruct;

class ShopDetailCollection extends ShopBasicCollection
{
    /**
     * @var ShopDetailStruct[]
     */
    protected $elements = [];

    public function getTemplates(): ShopTemplateBasicCollection
    {
        return new ShopTemplateBasicCollection(
            $this->fmap(function (ShopDetailStruct $shop) {
                return $shop->getTemplate();
            })
        );
    }

    public function getDocumentTemplates(): ShopTemplateBasicCollection
    {
        return new ShopTemplateBasicCollection(
            $this->fmap(function (ShopDetailStruct $shop) {
                return $shop->getDocumentTemplate();
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

    public function getFallbackTranslations(): ShopBasicCollection
    {
        return new ShopBasicCollection(
            $this->fmap(function (ShopDetailStruct $shop) {
                return $shop->getFallbackTranslation();
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

    public function getCountries(): CountryBasicCollection
    {
        return new CountryBasicCollection(
            $this->fmap(function (ShopDetailStruct $shop) {
                return $shop->getCountry();
            })
        );
    }

    public function getChildrenIds(): array
    {
        $ids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getChildren()->getIds() as $id) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    public function getChildren(): ShopBasicCollection
    {
        $collection = new ShopBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getChildren()->getElements());
        }

        return $collection;
    }

    public function getAllCurrencies(): CurrencyBasicCollection
    {
        $collection = new CurrencyBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getCurrencies()->getElements());
        }

        return $collection;
    }

    protected function getExpectedClass(): string
    {
        return ShopDetailStruct::class;
    }
}
