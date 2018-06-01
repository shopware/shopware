<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\Collection;

use Shopware\Core\Checkout\Order\Aggregate\OrderDeliveryPosition\Collection\OrderDeliveryPositionBasicCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\Struct\OrderLineItemDetailStruct;
use Shopware\Core\Checkout\Order\Collection\OrderBasicCollection;

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

    public function getOrderDeliveryPositionIds(): array
    {
        $ids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getOrderDeliveryPositions()->getIds() as $id) {
                $ids[] = $id;
            }
        }

        return $ids;
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
