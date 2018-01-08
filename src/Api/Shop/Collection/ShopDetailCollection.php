<?php declare(strict_types=1);

namespace Shopware\Api\Shop\Collection;

use Shopware\Api\Category\Collection\CategoryBasicCollection;
use Shopware\Api\Country\Collection\CountryBasicCollection;
use Shopware\Api\Currency\Collection\CurrencyBasicCollection;
use Shopware\Api\Customer\Collection\CustomerGroupBasicCollection;
use Shopware\Api\Payment\Collection\PaymentMethodBasicCollection;
use Shopware\Api\Seo\Collection\SeoUrlBasicCollection;
use Shopware\Api\Shipping\Collection\ShippingMethodBasicCollection;
use Shopware\Api\Shop\Struct\ShopDetailStruct;

class ShopDetailCollection extends ShopBasicCollection
{
    /**
     * @var ShopDetailStruct[]
     */
    protected $elements = [];

    public function getParents(): ShopBasicCollection
    {
        return new ShopBasicCollection(
            $this->fmap(function (ShopDetailStruct $shop) {
                return $shop->getParent();
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

    public function getSeoUrlIds(): array
    {
        $ids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getSeoUrls()->getIds() as $id) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    public function getSeoUrls(): SeoUrlBasicCollection
    {
        $collection = new SeoUrlBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getSeoUrls()->getElements());
        }

        return $collection;
    }

    public function getAllCurrencyIds(): array
    {
        $ids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getCurrencyIds() as $id) {
                $ids[] = $id;
            }
        }

        return $ids;
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
