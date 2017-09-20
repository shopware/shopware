<?php declare(strict_types=1);

namespace Shopware\OrderDelivery\Struct;

use Shopware\OrderDeliveryPosition\Struct\OrderDeliveryPositionBasicCollection;

class OrderDeliveryDetailCollection extends OrderDeliveryBasicCollection
{
    /**
     * @var OrderDeliveryDetailStruct[]
     */
    protected $elements = [];

    public function getPositionUuids(): array
    {
        $uuids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getPositions()->getUuids() as $uuid) {
                $uuids[] = $uuid;
            }
        }

        return $uuids;
    }

    public function getPositions(): OrderDeliveryPositionBasicCollection
    {
        $collection = new OrderDeliveryPositionBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getPositions()->getIterator()->getArrayCopy());
        }

        return $collection;
    }
}
