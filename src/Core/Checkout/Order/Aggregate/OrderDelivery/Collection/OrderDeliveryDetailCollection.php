<?php declare(strict_types=1);

namespace Shopware\Checkout\Order\Aggregate\OrderDelivery\Collection;

use Shopware\Checkout\Order\Aggregate\OrderDelivery\Collection\OrderDeliveryBasicCollection;
use Shopware\Checkout\Order\Collection\OrderBasicCollection;
use Shopware\Checkout\Order\Aggregate\OrderDeliveryPosition\Collection\OrderDeliveryPositionBasicCollection;
use Shopware\Checkout\Order\Aggregate\OrderDelivery\Struct\OrderDeliveryDetailStruct;

class OrderDeliveryDetailCollection extends OrderDeliveryBasicCollection
{
    /**
     * @var \Shopware\Checkout\Order\Aggregate\OrderDelivery\Struct\OrderDeliveryDetailStruct[]
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

    public function getPositions(): \Shopware\Checkout\Order\Aggregate\OrderDeliveryPosition\Collection\OrderDeliveryPositionBasicCollection
    {
        $collection = new \Shopware\Checkout\Order\Aggregate\OrderDeliveryPosition\Collection\OrderDeliveryPositionBasicCollection();
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
