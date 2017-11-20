<?php declare(strict_types=1);

namespace Shopware\Customer\Collection;

use Shopware\Customer\Struct\CustomerDetailStruct;
use Shopware\Order\Collection\OrderBasicCollection;
use Shopware\Shop\Collection\ShopBasicCollection;

class CustomerDetailCollection extends CustomerBasicCollection
{
    /**
     * @var CustomerDetailStruct[]
     */
    protected $elements = [];

    public function getMainShops(): ShopBasicCollection
    {
        return new ShopBasicCollection(
            $this->fmap(function (CustomerDetailStruct $customer) {
                return $customer->getMainShop();
            })
        );
    }

    public function getAddressUuids(): array
    {
        $uuids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getAddresses()->getUuids() as $uuid) {
                $uuids[] = $uuid;
            }
        }

        return $uuids;
    }

    public function getAddresses(): CustomerAddressBasicCollection
    {
        $collection = new CustomerAddressBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getAddresses()->getElements());
        }

        return $collection;
    }

    public function getOrderUuids(): array
    {
        $uuids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getOrders()->getUuids() as $uuid) {
                $uuids[] = $uuid;
            }
        }

        return $uuids;
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
