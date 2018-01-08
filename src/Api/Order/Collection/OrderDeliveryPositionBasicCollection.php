<?php declare(strict_types=1);

namespace Shopware\Api\Order\Collection;

use Shopware\Api\Entity\EntityCollection;
use Shopware\Api\Order\Struct\OrderDeliveryPositionBasicStruct;

class OrderDeliveryPositionBasicCollection extends EntityCollection
{
    /**
     * @var OrderDeliveryPositionBasicStruct[]
     */
    protected $elements = [];

    public function get(string $uuid): ? OrderDeliveryPositionBasicStruct
    {
        return parent::get($uuid);
    }

    public function current(): OrderDeliveryPositionBasicStruct
    {
        return parent::current();
    }

    public function getOrderDeliveryUuids(): array
    {
        return $this->fmap(function (OrderDeliveryPositionBasicStruct $orderDeliveryPosition) {
            return $orderDeliveryPosition->getOrderDeliveryUuid();
        });
    }

    public function filterByOrderDeliveryUuid(string $uuid): self
    {
        return $this->filter(function (OrderDeliveryPositionBasicStruct $orderDeliveryPosition) use ($uuid) {
            return $orderDeliveryPosition->getOrderDeliveryUuid() === $uuid;
        });
    }

    public function getOrderLineItemUuids(): array
    {
        return $this->fmap(function (OrderDeliveryPositionBasicStruct $orderDeliveryPosition) {
            return $orderDeliveryPosition->getOrderLineItemUuid();
        });
    }

    public function filterByOrderLineItemUuid(string $uuid): self
    {
        return $this->filter(function (OrderDeliveryPositionBasicStruct $orderDeliveryPosition) use ($uuid) {
            return $orderDeliveryPosition->getOrderLineItemUuid() === $uuid;
        });
    }

    public function getOrderLineItems(): OrderLineItemBasicCollection
    {
        return new OrderLineItemBasicCollection(
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
