<?php declare(strict_types=1);

namespace Shopware\Order\Collection;

use Shopware\Order\Struct\OrderAddressDetailStruct;

class OrderAddressDetailCollection extends OrderAddressBasicCollection
{
    /**
     * @var OrderAddressDetailStruct[]
     */
    protected $elements = [];

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

    public function getOrderDeliveryUuids(): array
    {
        $uuids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getOrderDeliveries()->getUuids() as $uuid) {
                $uuids[] = $uuid;
            }
        }

        return $uuids;
    }

    public function getOrderDeliveries(): OrderDeliveryBasicCollection
    {
        $collection = new OrderDeliveryBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getOrderDeliveries()->getElements());
        }

        return $collection;
    }

    protected function getExpectedClass(): string
    {
        return OrderAddressDetailStruct::class;
    }
}
