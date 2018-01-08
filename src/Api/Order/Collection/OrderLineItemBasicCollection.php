<?php declare(strict_types=1);

namespace Shopware\Api\Order\Collection;

use Shopware\Api\Entity\EntityCollection;
use Shopware\Api\Order\Struct\OrderLineItemBasicStruct;

class OrderLineItemBasicCollection extends EntityCollection
{
    /**
     * @var OrderLineItemBasicStruct[]
     */
    protected $elements = [];

    public function get(string $uuid): ? OrderLineItemBasicStruct
    {
        return parent::get($uuid);
    }

    public function current(): OrderLineItemBasicStruct
    {
        return parent::current();
    }

    public function getOrderUuids(): array
    {
        return $this->fmap(function (OrderLineItemBasicStruct $orderLineItem) {
            return $orderLineItem->getOrderUuid();
        });
    }

    public function filterByOrderUuid(string $uuid): self
    {
        return $this->filter(function (OrderLineItemBasicStruct $orderLineItem) use ($uuid) {
            return $orderLineItem->getOrderUuid() === $uuid;
        });
    }

    protected function getExpectedClass(): string
    {
        return OrderLineItemBasicStruct::class;
    }
}
