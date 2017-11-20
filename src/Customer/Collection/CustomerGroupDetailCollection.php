<?php declare(strict_types=1);

namespace Shopware\Customer\Collection;

use Shopware\Customer\Struct\CustomerGroupDetailStruct;
use Shopware\Product\Collection\ProductListingPriceBasicCollection;
use Shopware\Product\Collection\ProductPriceBasicCollection;
use Shopware\Shipping\Collection\ShippingMethodBasicCollection;
use Shopware\Shop\Collection\ShopBasicCollection;
use Shopware\Tax\Collection\TaxAreaRuleBasicCollection;

class CustomerGroupDetailCollection extends CustomerGroupBasicCollection
{
    /**
     * @var CustomerGroupDetailStruct[]
     */
    protected $elements = [];

    public function getCustomerUuids(): array
    {
        $uuids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getCustomers()->getUuids() as $uuid) {
                $uuids[] = $uuid;
            }
        }

        return $uuids;
    }

    public function getCustomers(): CustomerBasicCollection
    {
        $collection = new CustomerBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getCustomers()->getElements());
        }

        return $collection;
    }

    public function getDiscountUuids(): array
    {
        $uuids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getDiscounts()->getUuids() as $uuid) {
                $uuids[] = $uuid;
            }
        }

        return $uuids;
    }

    public function getDiscounts(): CustomerGroupDiscountBasicCollection
    {
        $collection = new CustomerGroupDiscountBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getDiscounts()->getElements());
        }

        return $collection;
    }

    public function getTranslationUuids(): array
    {
        $uuids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getTranslations()->getUuids() as $uuid) {
                $uuids[] = $uuid;
            }
        }

        return $uuids;
    }

    public function getTranslations(): CustomerGroupTranslationBasicCollection
    {
        $collection = new CustomerGroupTranslationBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getTranslations()->getElements());
        }

        return $collection;
    }

    public function getProductListingPriceUuids(): array
    {
        $uuids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getProductListingPrices()->getUuids() as $uuid) {
                $uuids[] = $uuid;
            }
        }

        return $uuids;
    }

    public function getProductListingPrices(): ProductListingPriceBasicCollection
    {
        $collection = new ProductListingPriceBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getProductListingPrices()->getElements());
        }

        return $collection;
    }

    public function getProductPriceUuids(): array
    {
        $uuids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getProductPrices()->getUuids() as $uuid) {
                $uuids[] = $uuid;
            }
        }

        return $uuids;
    }

    public function getProductPrices(): ProductPriceBasicCollection
    {
        $collection = new ProductPriceBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getProductPrices()->getElements());
        }

        return $collection;
    }

    public function getShippingMethodUuids(): array
    {
        $uuids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getShippingMethods()->getUuids() as $uuid) {
                $uuids[] = $uuid;
            }
        }

        return $uuids;
    }

    public function getShippingMethods(): ShippingMethodBasicCollection
    {
        $collection = new ShippingMethodBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getShippingMethods()->getElements());
        }

        return $collection;
    }

    public function getShopUuids(): array
    {
        $uuids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getShops()->getUuids() as $uuid) {
                $uuids[] = $uuid;
            }
        }

        return $uuids;
    }

    public function getShops(): ShopBasicCollection
    {
        $collection = new ShopBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getShops()->getElements());
        }

        return $collection;
    }

    public function getTaxAreaRuleUuids(): array
    {
        $uuids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getTaxAreaRules()->getUuids() as $uuid) {
                $uuids[] = $uuid;
            }
        }

        return $uuids;
    }

    public function getTaxAreaRules(): TaxAreaRuleBasicCollection
    {
        $collection = new TaxAreaRuleBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getTaxAreaRules()->getElements());
        }

        return $collection;
    }

    protected function getExpectedClass(): string
    {
        return CustomerGroupDetailStruct::class;
    }
}
