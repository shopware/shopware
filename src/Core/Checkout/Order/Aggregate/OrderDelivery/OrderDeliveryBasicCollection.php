<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Aggregate\OrderDelivery;

use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressBasicCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryBasicStruct;
use Shopware\Core\Checkout\Order\Aggregate\OrderState\OrderStateBasicCollection;
use Shopware\Core\Checkout\Shipping\ShippingMethodBasicCollection;
use Shopware\Core\Framework\ORM\EntityCollection;

class OrderDeliveryBasicCollection extends EntityCollection
{
    /**
     * @var \Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryBasicStruct[]
     */
    protected $elements = [];

    public function get(string $id): ? OrderDeliveryBasicStruct
    {
        return parent::get($id);
    }

    public function current(): OrderDeliveryBasicStruct
    {
        return parent::current();
    }

    public function getOrderIds(): array
    {
        return $this->fmap(function (OrderDeliveryBasicStruct $orderDelivery) {
            return $orderDelivery->getOrderId();
        });
    }

    public function filterByOrderId(string $id): self
    {
        return $this->filter(function (OrderDeliveryBasicStruct $orderDelivery) use ($id) {
            return $orderDelivery->getOrderId() === $id;
        });
    }

    public function getShippingAddressIds(): array
    {
        return $this->fmap(function (OrderDeliveryBasicStruct $orderDelivery) {
            return $orderDelivery->getShippingAddressId();
        });
    }

    public function filterByShippingAddressId(string $id): self
    {
        return $this->filter(function (OrderDeliveryBasicStruct $orderDelivery) use ($id) {
            return $orderDelivery->getShippingAddressId() === $id;
        });
    }

    public function getOrderStateIds(): array
    {
        return $this->fmap(function (OrderDeliveryBasicStruct $orderDelivery) {
            return $orderDelivery->getOrderStateId();
        });
    }

    public function filterByOrderStateId(string $id): self
    {
        return $this->filter(function (OrderDeliveryBasicStruct $orderDelivery) use ($id) {
            return $orderDelivery->getOrderStateId() === $id;
        });
    }

    public function getShippingMethodIds(): array
    {
        return $this->fmap(function (OrderDeliveryBasicStruct $orderDelivery) {
            return $orderDelivery->getShippingMethodId();
        });
    }

    public function filterByShippingMethodId(string $id): self
    {
        return $this->filter(function (OrderDeliveryBasicStruct $orderDelivery) use ($id) {
            return $orderDelivery->getShippingMethodId() === $id;
        });
    }

    public function getShippingAddress(): OrderAddressBasicCollection
    {
        return new \Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressBasicCollection(
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
