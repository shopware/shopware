<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\Collection;

use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\Struct\CustomerAddressBasicStruct;
use Shopware\Core\Checkout\Customer\Struct\CustomerBasicStruct;
use Shopware\Core\Framework\ORM\EntityCollection;
use Shopware\Core\System\Country\Aggregate\CountryState\Collection\CountryStateBasicCollection;
use Shopware\Core\System\Country\Collection\CountryBasicCollection;

class CustomerAddressBasicCollection extends EntityCollection
{
    /**
     * @var CustomerAddressBasicStruct[]
     */
    protected $elements = [];

    public function get(string $id): ? CustomerAddressBasicStruct
    {
        return parent::get($id);
    }

    public function current(): CustomerAddressBasicStruct
    {
        return parent::current();
    }

    public function getCustomerIds(): array
    {
        return $this->fmap(function (CustomerAddressBasicStruct $customerAddress) {
            return $customerAddress->getCustomerId();
        });
    }

    public function filterByCustomerId(string $id): self
    {
        return $this->filter(function (CustomerAddressBasicStruct $customerAddress) use ($id) {
            return $customerAddress->getCustomerId() === $id;
        });
    }

    public function getCountryIds(): array
    {
        return $this->fmap(function (CustomerAddressBasicStruct $customerAddress) {
            return $customerAddress->getCountryId();
        });
    }

    public function filterByCountryId(string $id): self
    {
        return $this->filter(function (CustomerAddressBasicStruct $customerAddress) use ($id) {
            return $customerAddress->getCountryId() === $id;
        });
    }

    public function getCountryStateIds(): array
    {
        return $this->fmap(function (CustomerAddressBasicStruct $customerAddress) {
            return $customerAddress->getCountryStateId();
        });
    }

    public function filterByCountryStateId(string $id): self
    {
        return $this->filter(function (CustomerAddressBasicStruct $customerAddress) use ($id) {
            return $customerAddress->getCountryStateId() === $id;
        });
    }

    public function getVatIds(): array
    {
        return $this->fmap(function (CustomerAddressBasicStruct $customerAddress) {
            return $customerAddress->getVatId();
        });
    }

    public function filterByVatId(string $id): self
    {
        return $this->filter(function (CustomerAddressBasicStruct $customerAddress) use ($id) {
            return $customerAddress->getVatId() === $id;
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

    public function getCountryStates(): \Shopware\Core\System\Country\Aggregate\CountryState\Collection\CountryStateBasicCollection
    {
        return new CountryStateBasicCollection(
            $this->fmap(function (CustomerAddressBasicStruct $customerAddress) {
                return $customerAddress->getCountryState();
            })
        );
    }

    public function sortByDefaultAddress(CustomerBasicStruct $customer): CustomerAddressBasicCollection
    {
        $this->sort(function (CustomerAddressBasicStruct $a, CustomerAddressBasicStruct $b) use ($customer) {
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
        return CustomerAddressBasicStruct::class;
    }
}
