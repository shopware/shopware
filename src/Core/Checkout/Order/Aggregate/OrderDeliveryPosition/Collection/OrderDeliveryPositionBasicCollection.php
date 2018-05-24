<?php declare(strict_types=1);

namespace Shopware\Checkout\Order\Aggregate\OrderDeliveryPosition\Collection;

use Shopware\Checkout\Order\Aggregate\OrderDeliveryPosition\Struct\OrderDeliveryPositionBasicStruct;
use Shopware\Framework\ORM\EntityCollection;

class OrderDeliveryPositionBasicCollection extends EntityCollection
{
    /**
     * @var OrderDeliveryPositionBasicStruct[]
     */
    protected $elements = [];

    public function get(string $id): ? OrderDeliveryPositionBasicStruct
    {
        return parent::get($id);
    }

    public function current(): OrderDeliveryPositionBasicStruct
    {
        return parent::current();
    }

    public function getOrderDeliveryIds(): array
    {
        return $this->fmap(function (OrderDeliveryPositionBasicStruct $orderDeliveryPosition) {
            return $orderDeliveryPosition->getOrderDeliveryId();
        });
    }

    public function filterByOrderDeliveryId(string $id): self
    {
        return $this->filter(function (OrderDeliveryPositionBasicStruct $orderDeliveryPosition) use ($id) {
            return $orderDeliveryPosition->getOrderDeliveryId() === $id;
        });
    }

    public function getOrderLineItemIds(): array
    {
        return $this->fmap(function (OrderDeliveryPositionBasicStruct $orderDeliveryPosition) {
            return $orderDeliveryPosition->getOrderLineItemId();
        });
    }

    public function filterByOrderLineItemId(string $id): self
    {
        return $this->filter(function (OrderDeliveryPositionBasicStruct $orderDeliveryPosition) use ($id) {
            return $orderDeliveryPosition->getOrderLineItemId() === $id;
        });
    }

    public function getOrderLineItems(): \Shopware\Checkout\Order\Aggregate\OrderLineItem\Collection\OrderLineItemBasicCollection
    {
        return new \Shopware\Checkout\Order\Aggregate\OrderLineItem\Collection\OrderLineItemBasicCollection(
            $this->fmap(function (OrderDeliveryPositionBasicStruct $orderDeliveryPosition) {
                return $orderDeliveryPosition->getOrderLineItem();
            })
        );
    }

    protected function getExpectedClass(): string
    {
        return OrderDeliveryPositionBasicStruct::class;
    }
}
