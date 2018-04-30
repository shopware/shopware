<?php declare(strict_types=1);

namespace Shopware\Api\Customer\Collection;

use Shopware\Api\Customer\Struct\CustomerDetailStruct;
use Shopware\Api\Order\Collection\OrderBasicCollection;

class CustomerDetailCollection extends CustomerBasicCollection
{
    /**
     * @var CustomerDetailStruct[]
     */
    protected $elements = [];

    public function getAddressIds(): array
    {
        $ids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getAddresses()->getIds() as $id) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    public function getAddresses(): CustomerAddressBasicCollection
    {
        $collection = new CustomerAddressBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getAddresses()->getElements());
        }

        return $collection;
    }

    public function getOrderIds(): array
    {
        $ids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getOrders()->getIds() as $id) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    public function getOrders(): OrderBasicCollection
    {
        $collection = new OrderBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getOrders()->getElements());
        }

        return $collection;
    }

    protected function getExpectedClass(): string
    {
        return CustomerDetailStruct::class;
    }
}
