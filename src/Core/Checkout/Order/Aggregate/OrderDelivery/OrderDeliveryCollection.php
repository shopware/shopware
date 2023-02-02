<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Aggregate\OrderDelivery;

use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressCollection;
use Shopware\Core\Checkout\Shipping\ShippingMethodCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<OrderDeliveryEntity>
 */
#[Package('customer-order')]
class OrderDeliveryCollection extends EntityCollection
{
    /**
     * @return list<string>
     */
    public function getOrderIds(): array
    {
        return $this->fmap(fn (OrderDeliveryEntity $orderDelivery) => $orderDelivery->getOrderId());
    }

    public function filterByOrderId(string $id): self
    {
        return $this->filter(fn (OrderDeliveryEntity $orderDelivery) => $orderDelivery->getOrderId() === $id);
    }

    /**
     * @return list<string>
     */
    public function getShippingAddressIds(): array
    {
        return $this->fmap(fn (OrderDeliveryEntity $orderDelivery) => $orderDelivery->getShippingOrderAddressId());
    }

    public function filterByShippingAddressId(string $id): self
    {
        return $this->filter(fn (OrderDeliveryEntity $orderDelivery) => $orderDelivery->getShippingOrderAddressId() === $id);
    }

    /**
     * @return list<string>
     */
    public function getShippingMethodIds(): array
    {
        return $this->fmap(fn (OrderDeliveryEntity $orderDelivery) => $orderDelivery->getShippingMethodId());
    }

    public function filterByShippingMethodId(string $id): self
    {
        return $this->filter(fn (OrderDeliveryEntity $orderDelivery) => $orderDelivery->getShippingMethodId() === $id);
    }

    public function getShippingAddress(): OrderAddressCollection
    {
        return new OrderAddressCollection(
            $this->fmap(fn (OrderDeliveryEntity $orderDelivery) => $orderDelivery->getShippingOrderAddress())
        );
    }

    public function getShippingMethods(): ShippingMethodCollection
    {
        return new ShippingMethodCollection(
            $this->fmap(fn (OrderDeliveryEntity $orderDelivery) => $orderDelivery->getShippingMethod())
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
