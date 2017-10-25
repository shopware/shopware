<?php declare(strict_types=1);

namespace Shopware\CustomerAddress\Struct;

use Shopware\AreaCountry\Struct\AreaCountryBasicCollection;
use Shopware\AreaCountryState\Struct\AreaCountryStateBasicCollection;
use Shopware\Framework\Struct\Collection;

class CustomerAddressBasicCollection extends Collection
{
    /**
     * @var CustomerAddressBasicStruct[]
     */
    protected $elements = [];

    public function add(CustomerAddressBasicStruct $customerAddress): void
    {
        $key = $this->getKey($customerAddress);
        $this->elements[$key] = $customerAddress;
    }

    public function remove(string $uuid): void
    {
        parent::doRemoveByKey($uuid);
    }

    public function removeElement(CustomerAddressBasicStruct $customerAddress): void
    {
        parent::doRemoveByKey($this->getKey($customerAddress));
    }

    public function exists(CustomerAddressBasicStruct $customerAddress): bool
    {
        return parent::has($this->getKey($customerAddress));
    }

    public function getList(array $uuids): CustomerAddressBasicCollection
    {
        return new self(array_intersect_key($this->elements, array_flip($uuids)));
    }

    public function get(string $uuid): ? CustomerAddressBasicStruct
    {
        if ($this->has($uuid)) {
            return $this->elements[$uuid];
        }

        return null;
    }

    public function getUuids(): array
    {
        return $this->fmap(function (CustomerAddressBasicStruct $customerAddress) {
            return $customerAddress->getUuid();
        });
    }

    public function merge(CustomerAddressBasicCollection $collection)
    {
        /** @var CustomerAddressBasicStruct $customerAddress */
        foreach ($collection as $customerAddress) {
            if ($this->has($this->getKey($customerAddress))) {
                continue;
            }
            $this->add($customerAddress);
        }
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

    public function getAreaCountryUuids(): array
    {
        return $this->fmap(function (CustomerAddressBasicStruct $customerAddress) {
            return $customerAddress->getAreaCountryUuid();
        });
    }

    public function filterByAreaCountryUuid(string $uuid): CustomerAddressBasicCollection
    {
        return $this->filter(function (CustomerAddressBasicStruct $customerAddress) use ($uuid) {
            return $customerAddress->getAreaCountryUuid() === $uuid;
        });
    }

    public function getAreaCountryStateUuids(): array
    {
        return $this->fmap(function (CustomerAddressBasicStruct $customerAddress) {
            return $customerAddress->getAreaCountryStateUuid();
        });
    }

    public function filterByAreaCountryStateUuid(string $uuid): CustomerAddressBasicCollection
    {
        return $this->filter(function (CustomerAddressBasicStruct $customerAddress) use ($uuid) {
            return $customerAddress->getAreaCountryStateUuid() === $uuid;
        });
    }

    public function getCountries(): AreaCountryBasicCollection
    {
        return new AreaCountryBasicCollection(
            $this->fmap(function (CustomerAddressBasicStruct $customerAddress) {
                return $customerAddress->getCountry();
            })
        );
    }

    public function getStates(): AreaCountryStateBasicCollection
    {
        return new AreaCountryStateBasicCollection(
            $this->fmap(function (CustomerAddressBasicStruct $customerAddress) {
                return $customerAddress->getState();
            })
        );
    }

    public function current(): CustomerAddressBasicStruct
    {
        return parent::current();
    }

    protected function getKey(CustomerAddressBasicStruct $element): string
    {
        return $element->getUuid();
    }
}
