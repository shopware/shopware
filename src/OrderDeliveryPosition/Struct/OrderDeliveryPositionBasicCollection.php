<?php declare(strict_types=1);

namespace Shopware\OrderDeliveryPosition\Struct;

use Shopware\Framework\Struct\Collection;
use Shopware\OrderLineItem\Struct\OrderLineItemBasicCollection;

class OrderDeliveryPositionBasicCollection extends Collection
{
    /**
     * @var OrderDeliveryPositionBasicStruct[]
     */
    protected $elements = [];

    public function add(OrderDeliveryPositionBasicStruct $orderDeliveryPosition): void
    {
        $key = $this->getKey($orderDeliveryPosition);
        $this->elements[$key] = $orderDeliveryPosition;
    }

    public function remove(string $uuid): void
    {
        parent::doRemoveByKey($uuid);
    }

    public function removeElement(OrderDeliveryPositionBasicStruct $orderDeliveryPosition): void
    {
        parent::doRemoveByKey($this->getKey($orderDeliveryPosition));
    }

    public function exists(OrderDeliveryPositionBasicStruct $orderDeliveryPosition): bool
    {
        return parent::has($this->getKey($orderDeliveryPosition));
    }

    public function getList(array $uuids): OrderDeliveryPositionBasicCollection
    {
        return new self(array_intersect_key($this->elements, array_flip($uuids)));
    }

    public function get(string $uuid): ? OrderDeliveryPositionBasicStruct
    {
        if ($this->has($uuid)) {
            return $this->elements[$uuid];
        }

        return null;
    }

    public function getUuids(): array
    {
        return $this->fmap(function (OrderDeliveryPositionBasicStruct $orderDeliveryPosition) {
            return $orderDeliveryPosition->getUuid();
        });
    }

    public function merge(OrderDeliveryPositionBasicCollection $collection)
    {
        /** @var OrderDeliveryPositionBasicStruct $orderDeliveryPosition */
        foreach ($collection as $orderDeliveryPosition) {
            if ($this->has($this->getKey($orderDeliveryPosition))) {
                continue;
            }
            $this->add($orderDeliveryPosition);
        }
    }

    public function getOrderDeliveryUuids(): array
    {
        return $this->fmap(function (OrderDeliveryPositionBasicStruct $orderDeliveryPosition) {
            return $orderDeliveryPosition->getOrderDeliveryUuid();
        });
    }

    public function filterByOrderDeliveryUuid(string $uuid): OrderDeliveryPositionBasicCollection
    {
        return $this->filter(function (OrderDeliveryPositionBasicStruct $orderDeliveryPosition) use ($uuid) {
            return $orderDeliveryPosition->getOrderDeliveryUuid() === $uuid;
        });
    }

    public function getOrderLineItemUuids(): array
    {
        return $this->fmap(function (OrderDeliveryPositionBasicStruct $orderDeliveryPosition) {
            return $orderDeliveryPosition->getOrderLineItemUuid();
        });
    }

    public function filterByOrderLineItemUuid(string $uuid): OrderDeliveryPositionBasicCollection
    {
        return $this->filter(function (OrderDeliveryPositionBasicStruct $orderDeliveryPosition) use ($uuid) {
            return $orderDeliveryPosition->getOrderLineItemUuid() === $uuid;
        });
    }

    public function getLineItems(): OrderLineItemBasicCollection
    {
        return new OrderLineItemBasicCollection(
            $this->fmap(function (OrderDeliveryPositionBasicStruct $orderDeliveryPosition) {
                return $orderDeliveryPosition->getLineItem();
            })
        );
    }

    protected function getKey(OrderDeliveryPositionBasicStruct $element): string
    {
        return $element->getUuid();
    }
}
