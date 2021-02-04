<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress;

use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\System\Country\Aggregate\CountryState\CountryStateCollection;
use Shopware\Core\System\Country\CountryCollection;

/**
 * @method void                       add(CustomerAddressEntity $entity)
 * @method void                       set(string $key, CustomerAddressEntity $entity)
 * @method CustomerAddressEntity[]    getIterator()
 * @method CustomerAddressEntity[]    getElements()
 * @method CustomerAddressEntity|null get(string $key)
 * @method CustomerAddressEntity|null first()
 * @method CustomerAddressEntity|null last()
 */
class CustomerAddressCollection extends EntityCollection
{
    public function getCustomerIds(): array
    {
        return $this->fmap(function (CustomerAddressEntity $customerAddress) {
            return $customerAddress->getCustomerId();
        });
    }

    public function filterByCustomerId(string $id): self
    {
        return $this->filter(function (CustomerAddressEntity $customerAddress) use ($id) {
            return $customerAddress->getCustomerId() === $id;
        });
    }

    public function getCountryIds(): array
    {
        return $this->fmap(function (CustomerAddressEntity $customerAddress) {
            return $customerAddress->getCountryId();
        });
    }

    public function filterByCountryId(string $id): self
    {
        return $this->filter(function (CustomerAddressEntity $customerAddress) use ($id) {
            return $customerAddress->getCountryId() === $id;
        });
    }

    public function getCountryStateIds(): array
    {
        return $this->fmap(function (CustomerAddressEntity $customerAddress) {
            return $customerAddress->getCountryStateId();
        });
    }

    public function filterByCountryStateId(string $id): self
    {
        return $this->filter(function (CustomerAddressEntity $customerAddress) use ($id) {
            return $customerAddress->getCountryStateId() === $id;
        });
    }

    /**
     * @deprecated tag:v6.4.0 - Will be removed and use CustomerCollection:getListVatIds() instead
     */
    public function getVatIds(): array
    {
        return $this->fmap(function (CustomerAddressEntity $customerAddress) {
            return $customerAddress->getVatId();
        });
    }

    /**
     * @deprecated tag:v6.4.0 - Will be removed and use CustomerCollection:filterByVatId() instead
     */
    public function filterByVatId(string $id): self
    {
        return $this->filter(function (CustomerAddressEntity $customerAddress) use ($id) {
            return $customerAddress->getVatId() === $id;
        });
    }

    public function getCountries(): CountryCollection
    {
        return new CountryCollection(
            $this->fmap(function (CustomerAddressEntity $customerAddress) {
                return $customerAddress->getCountry();
            })
        );
    }

    public function getCountryStates(): CountryStateCollection
    {
        return new CountryStateCollection(
            $this->fmap(function (CustomerAddressEntity $customerAddress) {
                return $customerAddress->getCountryState();
            })
        );
    }

    public function sortByDefaultAddress(CustomerEntity $customer): CustomerAddressCollection
    {
        $this->sort(function (CustomerAddressEntity $a, CustomerAddressEntity $b) use ($customer) {
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

    public function getApiAlias(): string
    {
        return 'customer_address_collection';
    }

    protected function getExpectedClass(): string
    {
        return CustomerAddressEntity::class;
    }
}
