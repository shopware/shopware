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

    public function getStateUuids(): array
    {
        $uuids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getStates()->getUuids() as $uuid) {
                $uuids[] = $uuid;
            }
        }

        return $uuids;
    }

    public function getStates(): CountryStateBasicCollection
    {
        $collection = new CountryStateBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getStates()->getElements());
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

    public function getTranslations(): CountryTranslationBasicCollection
    {
        $collection = new CountryTranslationBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getTranslations()->getElements());
        }

        return $collection;
    }

    public function getCustomerAddressUuids(): array
    {
        $uuids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getCustomerAddresses()->getUuids() as $uuid) {
                $uuids[] = $uuid;
            }
        }

        return $uuids;
    }

    public function getCustomerAddresses(): CustomerAddressBasicCollection
    {
        $collection = new CustomerAddressBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getCustomerAddresses()->getElements());
        }

        return $collection;
    }

    public function getOrderAddressUuids(): array
    {
        $uuids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getOrderAddresses()->getUuids() as $uuid) {
                $uuids[] = $uuid;
            }
        }

        return $uuids;
    }

    public function getOrderAddresses(): OrderAddressBasicCollection
    {
        $collection = new OrderAddressBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getOrderAddresses()->getElements());
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
        return CountryDetailStruct::class;
    }
}
