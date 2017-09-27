<?php declare(strict_types=1);

namespace Shopware\OrderLineItem\Struct;

use Shopware\Framework\Struct\Collection;

class OrderLineItemBasicCollection extends Collection
{
    /**
     * @var OrderLineItemBasicStruct[]
     */
    protected $elements = [];

    public function add(OrderLineItemBasicStruct $orderLineItem): void
    {
        $key = $this->getKey($orderLineItem);
        $this->elements[$key] = $orderLineItem;
    }

    public function remove(string $uuid): void
    {
        parent::doRemoveByKey($uuid);
    }

    public function removeElement(OrderLineItemBasicStruct $orderLineItem): void
    {
        parent::doRemoveByKey($this->getKey($orderLineItem));
    }

    public function exists(OrderLineItemBasicStruct $orderLineItem): bool
    {
        return parent::has($this->getKey($orderLineItem));
    }

    public function getList(array $uuids): OrderLineItemBasicCollection
    {
        return new self(array_intersect_key($this->elements, array_flip($uuids)));
    }

    public function get(string $uuid): ? OrderLineItemBasicStruct
    {
        if ($this->has($uuid)) {
            return $this->elements[$uuid];
        }

        return null;
    }

    public function getUuids(): array
    {
        return $this->fmap(function (OrderLineItemBasicStruct $orderLineItem) {
            return $orderLineItem->getUuid();
        });
    }

    public function merge(OrderLineItemBasicCollection $collection)
    {
        /** @var OrderLineItemBasicStruct $orderLineItem */
        foreach ($collection as $orderLineItem) {
            if ($this->has($this->getKey($orderLineItem))) {
                continue;
            }
            $this->add($orderLineItem);
        }
    }

    public function getOrderUuids(): array
    {
        return $this->fmap(function (OrderLineItemBasicStruct $orderLineItem) {
            return $orderLineItem->getOrderUuid();
        });
    }

    public function filterByOrderUuid(string $uuid): OrderLineItemBasicCollection
    {
        return $this->filter(function (OrderLineItemBasicStruct $orderLineItem) use ($uuid) {
            return $orderLineItem->getOrderUuid() === $uuid;
        });
    }

    protected function getKey(OrderLineItemBasicStruct $element): string
    {
        return $element->getUuid();
    }
}
