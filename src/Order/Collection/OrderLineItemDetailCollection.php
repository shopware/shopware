<?php declare(strict_types=1);

namespace Shopware\Order\Collection;

use Shopware\Order\Struct\OrderLineItemDetailStruct;

class OrderLineItemDetailCollection extends OrderLineItemBasicCollection
{
    /**
     * @var OrderLineItemDetailStruct[]
     */
    protected $elements = [];

    public function getOrders(): OrderBasicCollection
    {
        return new OrderBasicCollection(
            $this->fmap(function (OrderLineItemDetailStruct $orderLineItem) {
                return $orderLineItem->getOrder();
            })
        );
    }

    public function getOrderDeliveryPositionUuids(): array
    {
        $uuids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getOrderDeliveryPositions()->getUuids() as $uuid) {
                $uuids[] = $uuid;
            }
        }

        return $uuids;
    }

    public function getOrderDeliveryPositions(): OrderDeliveryPositionBasicCollection
    {
        $collection = new OrderDeliveryPositionBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getOrderDeliveryPositions()->getElements());
        }

        return $collection;
    }

    protected function getExpectedClass(): string
    {
        return OrderLineItemDetailStruct::class;
    }
}
