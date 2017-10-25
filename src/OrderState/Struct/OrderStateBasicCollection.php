<?php declare(strict_types=1);

namespace Shopware\OrderState\Struct;

use Shopware\Framework\Struct\Collection;

class OrderStateBasicCollection extends Collection
{
    /**
     * @var OrderStateBasicStruct[]
     */
    protected $elements = [];

    public function add(OrderStateBasicStruct $orderState): void
    {
        $key = $this->getKey($orderState);
        $this->elements[$key] = $orderState;
    }

    public function remove(string $uuid): void
    {
        parent::doRemoveByKey($uuid);
    }

    public function removeElement(OrderStateBasicStruct $orderState): void
    {
        parent::doRemoveByKey($this->getKey($orderState));
    }

    public function exists(OrderStateBasicStruct $orderState): bool
    {
        return parent::has($this->getKey($orderState));
    }

    public function getList(array $uuids): OrderStateBasicCollection
    {
        return new self(array_intersect_key($this->elements, array_flip($uuids)));
    }

    public function get(string $uuid): ? OrderStateBasicStruct
    {
        if ($this->has($uuid)) {
            return $this->elements[$uuid];
        }

        return null;
    }

    public function getUuids(): array
    {
        return $this->fmap(function (OrderStateBasicStruct $orderState) {
            return $orderState->getUuid();
        });
    }

    public function merge(OrderStateBasicCollection $collection)
    {
        /** @var OrderStateBasicStruct $orderState */
        foreach ($collection as $orderState) {
            if ($this->has($this->getKey($orderState))) {
                continue;
            }
            $this->add($orderState);
        }
    }

    public function current(): OrderStateBasicStruct
    {
        return parent::current();
    }

    protected function getKey(OrderStateBasicStruct $element): string
    {
        return $element->getUuid();
    }
}
