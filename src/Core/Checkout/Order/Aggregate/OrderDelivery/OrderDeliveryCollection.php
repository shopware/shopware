<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Aggregate\OrderDelivery;

use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressCollection;

use Shopware\Core\Checkout\Order\Aggregate\OrderState\OrderStateCollection;
use Shopware\Core\Checkout\Shipping\ShippingMethodCollection;
use Shopware\Core\Framework\ORM\EntityCollection;

class OrderDeliveryCollection extends EntityCollection
{
    /**
     * @var \Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryStruct[]
     */
    protected $elements = [];

    public function get(string $id): ? OrderDeliveryStruct
    {
        return parent::get($id);
    }

    public function current(): OrderDeliveryStruct
    {
        return parent::current();
    }

    public function getOrderIds(): array
    {
        return $this->fmap(function (OrderDeliveryStruct $orderDelivery) {
            return $orderDelivery->getOrderId();
        });
    }

    public function filterByOrderId(string $id): self
    {
        return $this->filter(function (OrderDeliveryStruct $orderDelivery) use ($id) {
            return $orderDelivery->getOrderId() === $id;
        });
    }

    public function getShippingAddressIds(): array
    {
        return $this->fmap(function (OrderDeliveryStruct $orderDelivery) {
            return $orderDelivery->getShippingAddressId();
        });
    }

    public function filterByShippingAddressId(string $id): self
    {
        return $this->filter(function (OrderDeliveryStruct $orderDelivery) use ($id) {
            return $orderDelivery->getShippingAddressId() === $id;
        });
    }

    public function getOrderStateIds(): array
    {
        return $this->fmap(function (OrderDeliveryStruct $orderDelivery) {
            return $orderDelivery->getOrderStateId();
        });
    }

    public function filterByOrderStateId(string $id): self
    {
        return $this->filter(function (OrderDeliveryStruct $orderDelivery) use ($id) {
            return $orderDelivery->getOrderStateId() === $id;
        });
    }

    public function getShippingMethodIds(): array
    {
        return $this->fmap(function (OrderDeliveryStruct $orderDelivery) {
            return $orderDelivery->getShippingMethodId();
        });
    }

    public function filterByShippingMethodId(string $id): self
    {
        return $this->filter(function (OrderDeliveryStruct $orderDelivery) use ($id) {
            return $orderDelivery->getShippingMethodId() === $id;
        });
    }

    public function getShippingAddress(): OrderAddressCollection
    {
        return new OrderAddressCollection(
            $this->fmap(function (OrderDeliveryStruct $orderDelivery) {
                return $orderDelivery->getShippingAddress();
            })
        );
    }

    public function getOrderStates(): OrderStateCollection
    {
        return new OrderStateCollection(
            $this->fmap(function (OrderDeliveryStruct $orderDelivery) {
                return $orderDelivery->getOrderState();
            })
        );
    }

    public function getShippingMethods(): ShippingMethodCollection
    {
        return new ShippingMethodCollection(
            $this->fmap(function (OrderDeliveryStruct $orderDelivery) {
                return $orderDelivery->getShippingMethod();
            })
        );
    }

    protected function getExpectedClass(): string
    {
        return OrderDeliveryStruct::class;
    }
}
