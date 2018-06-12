<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Aggregate\OrderDeliveryPosition;


use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Framework\ORM\EntityCollection;

class OrderDeliveryPositionCollection extends EntityCollection
{
    /**
     * @var OrderDeliveryPositionStruct[]
     */
    protected $elements = [];

    public function get(string $id): ? OrderDeliveryPositionStruct
    {
        return parent::get($id);
    }

    public function current(): OrderDeliveryPositionStruct
    {
        return parent::current();
    }

    public function getOrderDeliveryIds(): array
    {
        return $this->fmap(function (OrderDeliveryPositionStruct $orderDeliveryPosition) {
            return $orderDeliveryPosition->getOrderDeliveryId();
        });
    }

    public function filterByOrderDeliveryId(string $id): self
    {
        return $this->filter(function (OrderDeliveryPositionStruct $orderDeliveryPosition) use ($id) {
            return $orderDeliveryPosition->getOrderDeliveryId() === $id;
        });
    }

    public function getOrderLineItemIds(): array
    {
        return $this->fmap(function (OrderDeliveryPositionStruct $orderDeliveryPosition) {
            return $orderDeliveryPosition->getOrderLineItemId();
        });
    }

    public function filterByOrderLineItemId(string $id): self
    {
        return $this->filter(function (OrderDeliveryPositionStruct $orderDeliveryPosition) use ($id) {
            return $orderDeliveryPosition->getOrderLineItemId() === $id;
        });
    }

    public function getOrderLineItems(): OrderLineItemCollection
    {
        return new OrderLineItemCollection(
            $this->fmap(function (OrderDeliveryPositionStruct $orderDeliveryPosition) {
                return $orderDeliveryPosition->getOrderLineItem();
            })
        );
    }

    protected function getExpectedClass(): string
    {
        return OrderDeliveryPositionStruct::class;
    }
}
