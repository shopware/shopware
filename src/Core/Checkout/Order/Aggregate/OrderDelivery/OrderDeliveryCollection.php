<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Aggregate\OrderDelivery;

use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressCollection;
use Shopware\Core\Checkout\Shipping\ShippingMethodCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<OrderDeliveryEntity>
 */
class OrderDeliveryCollection extends EntityCollection
{
    /**
     * @return list<string>
     */
    public function getOrderIds(): array
    {
        return $this->fmap(function (OrderDeliveryEntity $orderDelivery) {
            return $orderDelivery->getOrderId();
        });
    }

    public function filterByOrderId(string $id): self
    {
        return $this->filter(function (OrderDeliveryEntity $orderDelivery) use ($id) {
            return $orderDelivery->getOrderId() === $id;
        });
    }

    /**
     * @return list<string>
     */
    public function getShippingAddressIds(): array
    {
        return $this->fmap(function (OrderDeliveryEntity $orderDelivery) {
            return $orderDelivery->getShippingOrderAddressId();
        });
    }

    public function filterByShippingAddressId(string $id): self
    {
        return $this->filter(function (OrderDeliveryEntity $orderDelivery) use ($id) {
            return $orderDelivery->getShippingOrderAddressId() === $id;
        });
    }

    /**
     * @return list<string>
     */
    public function getShippingMethodIds(): array
    {
        return $this->fmap(function (OrderDeliveryEntity $orderDelivery) {
            return $orderDelivery->getShippingMethodId();
        });
    }

    public function filterByShippingMethodId(string $id): self
    {
        return $this->filter(function (OrderDeliveryEntity $orderDelivery) use ($id) {
            return $orderDelivery->getShippingMethodId() === $id;
        });
    }

    public function getShippingAddress(): OrderAddressCollection
    {
        return new OrderAddressCollection(
            $this->fmap(function (OrderDeliveryEntity $orderDelivery) {
                return $orderDelivery->getShippingOrderAddress();
            })
        );
    }

    public function getShippingMethods(): ShippingMethodCollection
    {
        return new ShippingMethodCollection(
            $this->fmap(function (OrderDeliveryEntity $orderDelivery) {
                return $orderDelivery->getShippingMethod();
            })
        );
    }

    public function getApiAlias(): string
    {
        return 'order_delivery_collection';
    }

    protected function getExpectedClass(): string
    {
        return OrderDeliveryEntity::class;
    }
}
