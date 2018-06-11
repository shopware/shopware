<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\Collection;

use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\Struct\OrderDeliveryDetailStruct;
use Shopware\Core\Checkout\Order\Collection\OrderBasicCollection;

class OrderDeliveryDetailCollection extends OrderDeliveryBasicCollection
{
    /**
     * @var \Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\Struct\OrderDeliveryDetailStruct[]
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

    public function getPositions(): \Shopware\Core\Checkout\Order\Aggregate\OrderDeliveryPosition\Collection\OrderDeliveryPositionBasicCollection
    {
        $collection = new \Shopware\Core\Checkout\Order\Aggregate\OrderDeliveryPosition\Collection\OrderDeliveryPositionBasicCollection();
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
