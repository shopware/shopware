<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress;

use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Country\Aggregate\CountryState\CountryStateCollection;
use Shopware\Core\System\Country\CountryCollection;

/**
 * @extends EntityCollection<CustomerAddressEntity>
 */
#[Package('customer-order')]
class CustomerAddressCollection extends EntityCollection
{
    /**
     * @return list<string>
     */
    public function getCustomerIds(): array
    {
        return $this->fmap(fn (CustomerAddressEntity $customerAddress) => $customerAddress->getCustomerId());
    }

    public function filterByCustomerId(string $id): self
    {
        return $this->filter(fn (CustomerAddressEntity $customerAddress) => $customerAddress->getCustomerId() === $id);
    }

    /**
     * @return list<string>
     */
    public function getCountryIds(): array
    {
        return $this->fmap(fn (CustomerAddressEntity $customerAddress) => $customerAddress->getCountryId());
    }

    public function filterByCountryId(string $id): self
    {
        return $this->filter(fn (CustomerAddressEntity $customerAddress) => $customerAddress->getCountryId() === $id);
    }

    /**
     * @return list<string>
     */
    public function getCountryStateIds(): array
    {
        return $this->fmap(fn (CustomerAddressEntity $customerAddress) => $customerAddress->getCountryStateId());
    }

    public function filterByCountryStateId(string $id): self
    {
        return $this->filter(fn (CustomerAddressEntity $customerAddress) => $customerAddress->getCountryStateId() === $id);
    }

    public function getCountries(): CountryCollection
    {
        return new CountryCollection(
            $this->fmap(fn (CustomerAddressEntity $customerAddress) => $customerAddress->getCountry())
        );
    }

    public function getCountryStates(): CountryStateCollection
    {
        return new CountryStateCollection(
            $this->fmap(fn (CustomerAddressEntity $customerAddress) => $customerAddress->getCountryState())
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
