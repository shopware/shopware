<?php declare(strict_types=1);

namespace Shopware\OrderDelivery\Struct;

use Shopware\Framework\Struct\Collection;
use Shopware\OrderAddress\Struct\OrderAddressBasicCollection;
use Shopware\OrderState\Struct\OrderStateBasicCollection;
use Shopware\ShippingMethod\Struct\ShippingMethodBasicCollection;

class OrderDeliveryBasicCollection extends Collection
{
    /**
     * @var OrderDeliveryBasicStruct[]
     */
    protected $elements = [];

    public function add(OrderDeliveryBasicStruct $orderDelivery): void
    {
        $key = $this->getKey($orderDelivery);
        $this->elements[$key] = $orderDelivery;
    }

    public function remove(string $uuid): void
    {
        parent::doRemoveByKey($uuid);
    }

    public function removeElement(OrderDeliveryBasicStruct $orderDelivery): void
    {
        parent::doRemoveByKey($this->getKey($orderDelivery));
    }

    public function exists(OrderDeliveryBasicStruct $orderDelivery): bool
    {
        return parent::has($this->getKey($orderDelivery));
    }

    public function getList(array $uuids): OrderDeliveryBasicCollection
    {
        return new self(array_intersect_key($this->elements, array_flip($uuids)));
    }

    public function get(string $uuid): ? OrderDeliveryBasicStruct
    {
        if ($this->has($uuid)) {
            return $this->elements[$uuid];
        }

        return null;
    }

    public function getUuids(): array
    {
        return $this->fmap(function (OrderDeliveryBasicStruct $orderDelivery) {
            return $orderDelivery->getUuid();
        });
    }

    public function getOrderUuids(): array
    {
        return $this->fmap(function (OrderDeliveryBasicStruct $orderDelivery) {
            return $orderDelivery->getOrderUuid();
        });
    }

    public function filterByOrderUuid(string $uuid): OrderDeliveryBasicCollection
    {
        return $this->filter(function (OrderDeliveryBasicStruct $orderDelivery) use ($uuid) {
            return $orderDelivery->getOrderUuid() === $uuid;
        });
    }

    public function getShippingAddressUuids(): array
    {
        return $this->fmap(function (OrderDeliveryBasicStruct $orderDelivery) {
            return $orderDelivery->getShippingAddressUuid();
        });
    }

    public function filterByShippingAddressUuid(string $uuid): OrderDeliveryBasicCollection
    {
        return $this->filter(function (OrderDeliveryBasicStruct $orderDelivery) use ($uuid) {
            return $orderDelivery->getShippingAddressUuid() === $uuid;
        });
    }

    public function getOrderStateUuids(): array
    {
        return $this->fmap(function (OrderDeliveryBasicStruct $orderDelivery) {
            return $orderDelivery->getOrderStateUuid();
        });
    }

    public function filterByOrderStateUuid(string $uuid): OrderDeliveryBasicCollection
    {
        return $this->filter(function (OrderDeliveryBasicStruct $orderDelivery) use ($uuid) {
            return $orderDelivery->getOrderStateUuid() === $uuid;
        });
    }

    public function getShippingMethodUuids(): array
    {
        return $this->fmap(function (OrderDeliveryBasicStruct $orderDelivery) {
            return $orderDelivery->getShippingMethodUuid();
        });
    }

    public function filterByShippingMethodUuid(string $uuid): OrderDeliveryBasicCollection
    {
        return $this->filter(function (OrderDeliveryBasicStruct $orderDelivery) use ($uuid) {
            return $orderDelivery->getShippingMethodUuid() === $uuid;
        });
    }

    public function getStates(): OrderStateBasicCollection
    {
        return new OrderStateBasicCollection(
            $this->fmap(function (OrderDeliveryBasicStruct $orderDelivery) {
                return $orderDelivery->getState();
            })
        );
    }

    public function getShippingAddresses(): OrderAddressBasicCollection
    {
        return new OrderAddressBasicCollection(
            $this->fmap(function (OrderDeliveryBasicStruct $orderDelivery) {
                return $orderDelivery->getShippingAddress();
            })
        );
    }

    public function getShippingMethods(): ShippingMethodBasicCollection
    {
        return new ShippingMethodBasicCollection(
            $this->fmap(function (OrderDeliveryBasicStruct $orderDelivery) {
                return $orderDelivery->getShippingMethod();
            })
        );
    }

    protected function getKey(OrderDeliveryBasicStruct $element): string
    {
        return $element->getUuid();
    }
}
