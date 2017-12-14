<?php declare(strict_types=1);

namespace Shopware\Api\Order\Collection;

use Shopware\Api\Entity\EntityCollection;
use Shopware\Api\Order\Struct\OrderDeliveryBasicStruct;
use Shopware\Api\Shipping\Collection\ShippingMethodBasicCollection;

class OrderDeliveryBasicCollection extends EntityCollection
{
    /**
     * @var OrderDeliveryBasicStruct[]
     */
    protected $elements = [];

    public function get(string $uuid): ? OrderDeliveryBasicStruct
    {
        return parent::get($uuid);
    }

    public function current(): OrderDeliveryBasicStruct
    {
        return parent::current();
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

    public function getShippingAddress(): OrderAddressBasicCollection
    {
        return new OrderAddressBasicCollection(
            $this->fmap(function (OrderDeliveryBasicStruct $orderDelivery) {
                return $orderDelivery->getShippingAddress();
            })
        );
    }

    public function getOrderStates(): OrderStateBasicCollection
    {
        return new OrderStateBasicCollection(
            $this->fmap(function (OrderDeliveryBasicStruct $orderDelivery) {
                return $orderDelivery->getOrderState();
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

    protected function getExpectedClass(): string
    {
        return OrderDeliveryBasicStruct::class;
    }
}
