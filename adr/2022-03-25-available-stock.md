---
title: Available stock improvements
date: 2022-03-25
area: inventory
tags: [inventory, performance, stock]
---
Currently, the available stock calculation is performed on every update of a product. This is true if the product is updated via the API but also if it is ordered via the store api route. When an order is placed, this is triggered by `StockUpdater::lineItemWritten` and performs an update of the available stock by subtracting the stock with the quantity of open orders. If there are many open orders in the storage, this can lead to a bottleneck if many orders are executed at the same time, with the same products.

We have solved this problem by updating the available stock directly in the `CheckoutOrderPlaced` event with the ordered quantity:

```php
public function orderPlaced(CheckoutOrderPlacedEvent $event): void
{
    $ids = [];
    foreach ($event->getOrder()->getLineItems() as $lineItem) {
        if ($lineItem->getType() !== LineItem::PRODUCT_LINE_ITEM_TYPE) {
            continue;
        }

        if (!\array_key_exists($lineItem->getReferencedId(), $ids)) {
            $ids[$lineItem->getReferencedId()] = 0;
        }

        $ids[$lineItem->getReferencedId()] += $lineItem->getQuantity();
    }

    // order placed event is a high load event. Because of the high load, we simply reduce the quantity here instead of executing the high costs `update` function
    $query = new RetryableQuery(
        $this->connection,
        $this->connection->prepare('UPDATE product SET available_stock = available_stock - :quantity WHERE id = :id')
    );

    foreach ($ids as $id => $quantity) {
        $query->execute(['id' => Uuid::fromHexToBytes((string) $id), 'quantity' => $quantity]);
    }

    $this->updateAvailableFlag(\array_keys($ids), $event->getContext());
}
```

To prevent executing the `lineItemWritten` logic in addition to the `CheckoutOrderPlaced` logic, we set a context state within the `CartOrderRoute`, which we can then query in the event listener and skip the process:

```php
public function lineItemWritten(EntityWrittenEvent $event): void
{
    $ids = [];

    // we don't want to trigger to `update` method when we are inside the order process
    if ($event->getContext()->hasState('checkout-order-route')) {
        return;
    }
    
    //...
}
```

In addition to this optimization, we only perform a stock update if one of the three relevant fields (`stock`, `minPurchase`, `isCloseout`) has changed. This is checked in the `ProductIndexer` within the `update` method:
```php
$stocks = $event->getPrimaryKeysWithPropertyChange(ProductDefinition::ENTITY_NAME, ['stock', 'isCloseout', 'minPurchase']);
$this->stockUpdater->update($stocks, $event->getContext());
```
