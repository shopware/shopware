<?php declare(strict_types=1);

namespace Shopware\Country\Collection;

use Shopware\Country\Struct\CountryStateDetailStruct;
use Shopware\Customer\Collection\CustomerAddressBasicCollection;
use Shopware\Order\Collection\OrderAddressBasicCollection;
use Shopware\Tax\Collection\TaxAreaRuleBasicCollection;

class CountryStateDetailCollection extends CountryStateBasicCollection
{
    /**
     * @var CountryStateDetailStruct[]
     */
    protected $elements = [];

    public function getCountries(): CountryBasicCollection
    {
        return new CountryBasicCollection(
            $this->fmap(function (CountryStateDetailStruct $countryState) {
                return $countryState->getCountry();
            })
        );
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

    public function getTranslations(): CountryStateTranslationBasicCollection
    {
        $collection = new CountryStateTranslationBasicCollection();
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
        return CountryStateDetailStruct::class;
    }
}
