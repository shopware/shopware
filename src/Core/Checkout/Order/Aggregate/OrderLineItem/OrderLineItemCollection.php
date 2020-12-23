<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Aggregate\OrderLineItem;

use Shopware\Core\Checkout\Cart\Price\Struct\PriceCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;

/**
 * @method void                     add(OrderLineItemEntity $entity)
 * @method void                     set(string $key, OrderLineItemEntity $entity)
 * @method OrderLineItemEntity[]    getIterator()
 * @method OrderLineItemEntity[]    getElements()
 * @method OrderLineItemEntity|null get(string $key)
 * @method OrderLineItemEntity|null first()
 * @method OrderLineItemEntity|null last()
 */
class OrderLineItemCollection extends EntityCollection
{
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

    public function sortByCreationDate(string $sortDirection = FieldSorting::ASCENDING): void
    {
        $this->sort(function (OrderLineItemEntity $a, OrderLineItemEntity $b) use ($sortDirection) {
            if ($sortDirection === FieldSorting::ASCENDING) {
                return $a->getCreatedAt() > $b->getCreatedAt();
            }

            return $a->getCreatedAt() < $b->getCreatedAt();
        });
    }

    public function sortByPosition(): void
    {
        $this->sort(function (OrderLineItemEntity $a, OrderLineItemEntity $b) {
            return $a->getPosition() > $b->getPosition();
        });
    }

    public function getPayloadsProperty(string $property): array
    {
        return $this->fmap(function (OrderLineItemEntity $lineItem) use ($property) {
            if (\array_key_exists($property, $lineItem->getPayload())) {
                return $lineItem->getPayload()[$property];
            }

            return null;
        });
    }

    public function filterByType(string $type): self
    {
        return $this->filter(function (OrderLineItemEntity $lineItem) use ($type) {
            return $lineItem->getType() === $type;
        });
    }

    public function getApiAlias(): string
    {
        return 'order_line_item_collection';
    }

    public function getPrices(): PriceCollection
    {
        return new PriceCollection(
            array_filter(array_map(static function (OrderLineItemEntity $orderLineItem) {
                return $orderLineItem->getPrice();
            }, array_values($this->getElements())))
        );
    }

    protected function getExpectedClass(): string
    {
        return OrderLineItemEntity::class;
    }
}
