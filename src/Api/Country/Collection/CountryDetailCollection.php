<?php declare(strict_types=1);

namespace Shopware\Api\Country\Collection;

use Shopware\Api\Country\Struct\CountryDetailStruct;
use Shopware\Api\Customer\Collection\CustomerAddressBasicCollection;
use Shopware\Api\Order\Collection\OrderAddressBasicCollection;
use Shopware\Api\Shop\Collection\ShopBasicCollection;
use Shopware\Api\Tax\Collection\TaxAreaRuleBasicCollection;

class CountryDetailCollection extends CountryBasicCollection
{
    /**
     * @var CountryDetailStruct[]
     */
    protected $elements = [];

    public function getAreas(): CountryAreaBasicCollection
    {
        return new CountryAreaBasicCollection(
            $this->fmap(function (CountryDetailStruct $country) {
                return $country->getArea();
            })
        );
    }

    public function getStateIds(): array
    {
        $ids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getStates()->getIds() as $id) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    public function getStates(): CountryStateBasicCollection
    {
        $collection = new CountryStateBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getStates()->getElements());
        }

        return $collection;
    }

    public function getTranslationIds(): array
    {
        $ids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getTranslations()->getIds() as $id) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    public function getTranslations(): CountryTranslationBasicCollection
    {
        $collection = new CountryTranslationBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getTranslations()->getElements());
        }

        return $collection;
    }

    public function getCustomerAddressIds(): array
    {
        $ids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getCustomerAddresses()->getIds() as $id) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    public function getCustomerAddresses(): CustomerAddressBasicCollection
    {
        $collection = new CustomerAddressBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getCustomerAddresses()->getElements());
        }

        return $collection;
    }

    public function getOrderAddressIds(): array
    {
        $ids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getOrderAddresses()->getIds() as $id) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    public function getOrderAddresses(): OrderAddressBasicCollection
    {
        $collection = new OrderAddressBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getOrderAddresses()->getElements());
        }

        return $collection;
    }

    public function getShopIds(): array
    {
        $ids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getShops()->getIds() as $id) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    public function getShops(): ShopBasicCollection
    {
        $collection = new ShopBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getShops()->getElements());
        }

        return $collection;
    }

    public function getTaxAreaRuleIds(): array
    {
        $ids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getTaxAreaRules()->getIds() as $id) {
                $ids[] = $id;
            }
        }

        return $ids;
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
        return CountryDetailStruct::class;
    }
}
