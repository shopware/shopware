<?php declare(strict_types=1);

namespace Shopware\Customer\Collection;

use Shopware\Api\Entity\EntityCollection;
use Shopware\Country\Collection\CountryBasicCollection;
use Shopware\Country\Collection\CountryStateBasicCollection;
use Shopware\Customer\Struct\CustomerAddressBasicStruct;

class CustomerAddressBasicCollection extends EntityCollection
{
    /**
     * @var CustomerAddressBasicStruct[]
     */
    protected $elements = [];

    public function get(string $uuid): ? CustomerAddressBasicStruct
    {
        return parent::get($uuid);
    }

    public function current(): CustomerAddressBasicStruct
    {
        return parent::current();
    }

    public function getCustomerUuids(): array
    {
        return $this->fmap(function (CustomerAddressBasicStruct $customerAddress) {
            return $customerAddress->getCustomerUuid();
        });
    }

    public function filterByCustomerUuid(string $uuid): CustomerAddressBasicCollection
    {
        return $this->filter(function (CustomerAddressBasicStruct $customerAddress) use ($uuid) {
            return $customerAddress->getCustomerUuid() === $uuid;
        });
    }

    public function getCountryUuids(): array
    {
        return $this->fmap(function (CustomerAddressBasicStruct $customerAddress) {
            return $customerAddress->getCountryUuid();
        });
    }

    public function filterByCountryUuid(string $uuid): CustomerAddressBasicCollection
    {
        return $this->filter(function (CustomerAddressBasicStruct $customerAddress) use ($uuid) {
            return $customerAddress->getCountryUuid() === $uuid;
        });
    }

    public function getCountryStateUuids(): array
    {
        return $this->fmap(function (CustomerAddressBasicStruct $customerAddress) {
            return $customerAddress->getCountryStateUuid();
        });
    }

    public function filterByCountryStateUuid(string $uuid): CustomerAddressBasicCollection
    {
        return $this->filter(function (CustomerAddressBasicStruct $customerAddress) use ($uuid) {
            return $customerAddress->getCountryStateUuid() === $uuid;
        });
    }

    public function getCountries(): CountryBasicCollection
    {
        return new CountryBasicCollection(
            $this->fmap(function (CustomerAddressBasicStruct $customerAddress) {
                return $customerAddress->getCountry();
            })
        );
    }

    public function getCountryStates(): CountryStateBasicCollection
    {
        return new CountryStateBasicCollection(
            $this->fmap(function (CustomerAddressBasicStruct $customerAddress) {
                return $customerAddress->getCountryState();
            })
        );
    }

    protected function getExpectedClass(): string
    {
        return CustomerAddressBasicStruct::class;
    }
}
