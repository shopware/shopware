<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress;

use Shopware\Core\Checkout\Customer\CustomerStruct;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\System\Country\Aggregate\CountryState\CountryStateCollection;
use Shopware\Core\System\Country\CountryCollection;

class CustomerAddressCollection extends EntityCollection
{
    /**
     * @var CustomerAddressStruct[]
     */
    protected $elements = [];

    public function get(string $id): ? CustomerAddressStruct
    {
        return parent::get($id);
    }

    public function current(): CustomerAddressStruct
    {
        return parent::current();
    }

    public function getCustomerIds(): array
    {
        return $this->fmap(function (CustomerAddressStruct $customerAddress) {
            return $customerAddress->getCustomerId();
        });
    }

    public function filterByCustomerId(string $id): self
    {
        return $this->filter(function (CustomerAddressStruct $customerAddress) use ($id) {
            return $customerAddress->getCustomerId() === $id;
        });
    }

    public function getCountryIds(): array
    {
        return $this->fmap(function (CustomerAddressStruct $customerAddress) {
            return $customerAddress->getCountryId();
        });
    }

    public function filterByCountryId(string $id): self
    {
        return $this->filter(function (CustomerAddressStruct $customerAddress) use ($id) {
            return $customerAddress->getCountryId() === $id;
        });
    }

    public function getCountryStateIds(): array
    {
        return $this->fmap(function (CustomerAddressStruct $customerAddress) {
            return $customerAddress->getCountryStateId();
        });
    }

    public function filterByCountryStateId(string $id): self
    {
        return $this->filter(function (CustomerAddressStruct $customerAddress) use ($id) {
            return $customerAddress->getCountryStateId() === $id;
        });
    }

    public function getVatIds(): array
    {
        return $this->fmap(function (CustomerAddressStruct $customerAddress) {
            return $customerAddress->getVatId();
        });
    }

    public function filterByVatId(string $id): self
    {
        return $this->filter(function (CustomerAddressStruct $customerAddress) use ($id) {
            return $customerAddress->getVatId() === $id;
        });
    }

    public function getCountries(): CountryCollection
    {
        return new CountryCollection(
            $this->fmap(function (CustomerAddressStruct $customerAddress) {
                return $customerAddress->getCountry();
            })
        );
    }

    public function getCountryStates(): CountryStateCollection
    {
        return new CountryStateCollection(
            $this->fmap(function (CustomerAddressStruct $customerAddress) {
                return $customerAddress->getCountryState();
            })
        );
    }

    public function sortByDefaultAddress(CustomerStruct $customer): CustomerAddressCollection
    {
        $this->sort(function (CustomerAddressStruct $a, CustomerAddressStruct $b) use ($customer) {
            if ($a->getId() === $customer->getDefaultBillingAddressId() || $a->getId() === $customer->getDefaultShippingAddressId()) {
                return -1;
            }

            if ($b->getId() === $customer->getDefaultBillingAddressId() || $b->getId() === $customer->getDefaultShippingAddressId()) {
                return 1;
            }

            return 0;
        });

        return $this;
    }

    protected function getExpectedClass(): string
    {
        return CustomerAddressStruct::class;
    }
}
