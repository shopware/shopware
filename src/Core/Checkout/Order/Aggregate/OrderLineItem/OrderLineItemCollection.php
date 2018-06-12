<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Aggregate\OrderLineItem;

use Shopware\Core\Framework\ORM\EntityCollection;

class OrderLineItemCollection extends EntityCollection
{
    /**
     * @var OrderLineItemStruct[]
     */
    protected $elements = [];

    public function get(string $id): ? OrderLineItemStruct
    {
        return parent::get($id);
    }

    public function current(): OrderLineItemStruct
    {
        return parent::current();
    }

    public function getOrderIds(): array
    {
        return $this->fmap(function (OrderLineItemStruct $orderLineItem) {
            return $orderLineItem->getOrderId();
        });
    }

    public function filterByOrderId(string $id): self
    {
        return $this->filter(function (OrderLineItemStruct $orderLineItem) use ($id) {
            return $orderLineItem->getOrderId() === $id;
        });
    }

    protected function getExpectedClass(): string
    {
        return OrderLineItemStruct::class;
    }
}
