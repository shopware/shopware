<?php declare(strict_types=1);

namespace Shopware\Customer\Collection;

use Shopware\Customer\Struct\CustomerAddressDetailStruct;

class CustomerAddressDetailCollection extends CustomerAddressBasicCollection
{
    /**
     * @var CustomerAddressDetailStruct[]
     */
    protected $elements = [];

    public function getCustomers(): CustomerBasicCollection
    {
        return new CustomerBasicCollection(
            $this->fmap(function (CustomerAddressDetailStruct $customerAddress) {
                return $customerAddress->getCustomer();
            })
        );
    }

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

    protected function getExpectedClass(): string
    {
        return CustomerAddressDetailStruct::class;
    }
}
