<?php declare(strict_types=1);

namespace Shopware\Checkout\Order\Collection;

use Shopware\Checkout\Order\Struct\OrderAddressDetailStruct;

class OrderAddressDetailCollection extends OrderAddressBasicCollection
{
    /**
     * @var OrderAddressDetailStruct[]
     */
    protected $elements = [];

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

    public function getOrderDeliveryIds(): array
    {
        $ids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getOrderDeliveries()->getIds() as $id) {
                $ids[] = $id;
            }
        }

        return $ids;
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
