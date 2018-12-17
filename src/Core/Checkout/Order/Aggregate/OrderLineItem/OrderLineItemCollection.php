<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Aggregate\OrderLineItem;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

class OrderLineItemCollection extends EntityCollection
{
    /**
     * @var OrderLineItemEntity[]
     */
    protected $elements = [];

    public function get(string $id): ? OrderLineItemEntity
    {
        return parent::get($id);
    }

    public function current(): OrderLineItemEntity
    {
        return parent::current();
    }

    public function getOrderIds(): array
    {
        return $this->fmap(function (OrderLineItemEntity $orderLineItem) {
            return $orderLineItem->getOrderId();
        });
    }

    public function filterByOrderId(string $id): self
    {
        return $this->filter(function (OrderLineItemEntity $orderLineItem) use ($id) {
            return $orderLineItem->getOrderId() === $id;
        });
    }

    protected function getExpectedClass(): string
    {
        return OrderLineItemEntity::class;
    }
}
