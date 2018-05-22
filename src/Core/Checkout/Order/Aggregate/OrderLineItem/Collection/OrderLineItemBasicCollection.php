<?php declare(strict_types=1);

namespace Shopware\Checkout\Order\Aggregate\OrderLineItem\Collection;

use Shopware\Checkout\Order\Aggregate\OrderLineItem\Struct\OrderLineItemBasicStruct;
use Shopware\Framework\ORM\EntityCollection;

class OrderLineItemBasicCollection extends EntityCollection
{
    /**
     * @var \Shopware\Checkout\Order\Aggregate\OrderLineItem\Struct\OrderLineItemBasicStruct[]
     */
    protected $elements = [];

    public function get(string $id): ? OrderLineItemBasicStruct
    {
        return parent::get($id);
    }

    public function current(): OrderLineItemBasicStruct
    {
        return parent::current();
    }

    public function getOrderIds(): array
    {
        return $this->fmap(function (OrderLineItemBasicStruct $orderLineItem) {
            return $orderLineItem->getOrderId();
        });
    }

    public function filterByOrderId(string $id): self
    {
        return $this->filter(function (OrderLineItemBasicStruct $orderLineItem) use ($id) {
            return $orderLineItem->getOrderId() === $id;
        });
    }

    protected function getExpectedClass(): string
    {
        return OrderLineItemBasicStruct::class;
    }
}
