<?php declare(strict_types=1);

namespace Shopware\Order\Collection;

use Shopware\Order\Struct\OrderDetailStruct;

class OrderDetailCollection extends OrderBasicCollection
{
    /**
     * @var OrderDetailStruct[]
     */
    protected $elements = [];

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
            $collection->fill($element->getDeliveries()->getElements());
        }

        return $collection;
    }

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
            $collection->fill($element->getLineItems()->getElements());
        }

        return $collection;
    }

    protected function getExpectedClass(): string
    {
        return OrderDetailStruct::class;
    }
}
