<?php declare(strict_types=1);

namespace Shopware\Checkout\Order\Collection;

use Shopware\Checkout\Order\Struct\OrderDeliveryDetailStruct;

class OrderDeliveryDetailCollection extends OrderDeliveryBasicCollection
{
    /**
     * @var OrderDeliveryDetailStruct[]
     */
    protected $elements = [];

    public function getOrders(): OrderBasicCollection
    {
        return new OrderBasicCollection(
            $this->fmap(function (OrderDeliveryDetailStruct $orderDelivery) {
                return $orderDelivery->getOrder();
            })
        );
    }

    public function getPositionIds(): array
    {
        $ids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getPositions()->getIds() as $id) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    public function getPositions(): OrderDeliveryPositionBasicCollection
    {
        $collection = new OrderDeliveryPositionBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getPositions()->getElements());
        }

        return $collection;
    }

    protected function getExpectedClass(): string
    {
        return OrderDeliveryDetailStruct::class;
    }
}
