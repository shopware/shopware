<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Aggregate\OrderDeliveryPosition;

use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void                             add(OrderDeliveryPositionEntity $entity)
 * @method void                             set(string $key, OrderDeliveryPositionEntity $entity)
 * @method OrderDeliveryPositionEntity[]    getIterator()
 * @method OrderDeliveryPositionEntity[]    getElements()
 * @method OrderDeliveryPositionEntity|null get(string $key)
 * @method OrderDeliveryPositionEntity|null first()
 * @method OrderDeliveryPositionEntity|null last()
 */
class OrderDeliveryPositionCollection extends EntityCollection
{
    public function getOrderDeliveryIds(): array
    {
        return $this->fmap(function (OrderDeliveryPositionEntity $orderDeliveryPosition) {
            return $orderDeliveryPosition->getOrderDeliveryId();
        });
    }

    public function filterByOrderDeliveryId(string $id): self
    {
        return $this->filter(function (OrderDeliveryPositionEntity $orderDeliveryPosition) use ($id) {
            return $orderDeliveryPosition->getOrderDeliveryId() === $id;
        });
    }

    public function getOrderLineItemIds(): array
    {
        return $this->fmap(function (OrderDeliveryPositionEntity $orderDeliveryPosition) {
            return $orderDeliveryPosition->getOrderLineItemId();
        });
    }

    public function filterByOrderLineItemId(string $id): self
    {
        return $this->filter(function (OrderDeliveryPositionEntity $orderDeliveryPosition) use ($id) {
            return $orderDeliveryPosition->getOrderLineItemId() === $id;
        });
    }

    public function getOrderLineItems(): OrderLineItemCollection
    {
        return new OrderLineItemCollection(
            $this->fmap(function (OrderDeliveryPositionEntity $orderDeliveryPosition) {
                return $orderDeliveryPosition->getOrderLineItem();
            })
        );
    }

    public function getApiAlias(): string
    {
        return 'order_delivery_position_collection';
    }

    protected function getExpectedClass(): string
    {
        return OrderDeliveryPositionEntity::class;
    }
}
