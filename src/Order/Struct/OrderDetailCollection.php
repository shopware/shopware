<?php declare(strict_types=1);

namespace Shopware\Order\Struct;

use Shopware\OrderDelivery\Struct\OrderDeliveryBasicCollection;
use Shopware\OrderLineItem\Struct\OrderLineItemBasicCollection;

class OrderDetailCollection extends OrderBasicCollection
{
    /**
     * @var OrderDetailStruct[]
     */
    protected $elements = [];

    public function getLineItemUuids(): array
    {
        $uuids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getLineItems()->getUuids() as $uuid) {
                $uuids[] = $uuid;
            }
        }

        return $uuids;
    }

    public function getLineItems(): OrderLineItemBasicCollection
    {
        $collection = new OrderLineItemBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getLineItems()->getIterator()->getArrayCopy());
        }

        return $collection;
    }

    public function getDeliveryUuids(): array
    {
        $uuids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getDeliveries()->getUuids() as $uuid) {
                $uuids[] = $uuid;
            }
        }

        return $uuids;
    }

    public function getDeliveries(): OrderDeliveryBasicCollection
    {
        $collection = new OrderDeliveryBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getDeliveries()->getIterator()->getArrayCopy());
        }

        return $collection;
    }
}
