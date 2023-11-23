---
title: Stock Manipulation API
date: 2023-04-25
area: core
tags: [stock]
---

## Context

The stock handling in Shopware 6 is currently not very flexible and does not support many common use cases. 

* It's not possible to easily replace the loading of stocks with a custom implementation, for example one that communicates with an ERP.
* It's not possible to easily modify how stock is increased/decreased throughout the order lifecycle.
* Available stock calculation is very slow on large catalogs.
* Stock is stored as two distinct values: stock and available stock. This is due to the fact that stock is not reduced until an order is set as completed. Therefore, the available stock is calculated as the stock minus all open orders. This is unnecessarily complex.

## Decision

We have only one field `stock` in the product definition which always has a real time calculated value.

The `stock` value should be correctly updated as an order and its line items transition through the various states. Eg, stock is decremented when an order is placed. If it is cancelled, the stock is increased, and so on.

We have a clear API for manipulating stock which can be extended and supports arbitrary data, which could, for example, support features such as multi warehouse inventory.

We have a way to disable the stock handling behavior of Shopware.

### New feature flag

We introduce a new feature flag `STOCK_HANDLING` to allow people to opt in to the new stock handling behavior immediately. In 6.6 the flag will be removed and the new stock handling will be activated by default.

### Abstract Stock Storage

We will introduce a new `AbstractStockStorage`. The API will be as follows:

```php
<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Stock;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

#[Package('inventory')]
abstract class AbstractStockStorage
{
    abstract public function getDecorated(): self;

    /**
     * This method provides an extension point to augment the stock data when it is loaded.
     *
     * This method is called when loading products via:
     * * \Shopware\Core\Content\Product\SalesChannel\Detail\AvailableCombinationLoader
     * * \Shopware\Core\Content\Product\Stock\LoadProductStockSubscriber
     *
     * This data will be set directly on the products, overwriting their existing values. Furthermore, the keys specified below and any extra data will be added
     * as an array extension to the product under the key `stock_data`.
     */
    abstract public function load(StockLoadRequest $stockRequest, SalesChannelContext $context): StockDataCollection;

    /**
     * This method should be used to update the stock value of a product for a given order item change.
     *
     * @param list<StockAlteration> $changes
     */
    abstract public function alter(array $changes, Context $context): void;

    /**
     * This method is executed when a product is created or updated. It can be used to perform some calculations such as update the `available` flag based on the new stock level.
     *
     * @param list<string> $productIds
     */
    abstract public function index(array $productIds, Context $context): void;
}
```

With a few DTOs:

```php
<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Stock;

use Shopware\Core\Framework\Log\Package;

#[Package('inventory')]
class StockDataCollection
{
    public function add(StockData $stock): void

    public function getStockForProductId(string $productId): ?StockData

    /**
     * @return array<StockData>
     */
    public function all(): array
    {
    }
}
```

```php
<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Stock;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

#[Package('inventory')]
final class StockData extends Struct
{
    public function __construct(
        public readonly string $productId,
        public readonly int $stock,
        public readonly bool $available,
        public readonly ?int $minPurchase = null,
        public readonly ?int $maxPurchase = null,
        public readonly ?bool $isCloseout = null,
    ) {
    }

    public static function fromArray(array $info): self
    {
    }
}
```

```php
<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Stock;

use Shopware\Core\Framework\Log\Package;

#[Package('core')]
final class StockAlteration
{
    public function __construct(
        public readonly string $lineItemId,
        public readonly string $productId,
        public readonly int $quantityBefore,
        public readonly int $newQuantity
    ) {
    }

    public function quantityDelta(): int
    {
    }
}

```

The `alter` method receives a list of changes. Each change corresponds to a line item change. It contains the line item ID, the product ID and the before and after quantity of the line item.

This API encapsulates all the scenarios an order may transition through.

### Introduce a new `BeforeWriteEvent` event

We will introduce a new event to the `EntityWriteGateway` service. Much like `BeforeDeleteEvent` it will be dispatched before any commands are written. It allows for subscribers to add success and error callbacks via the methods:

* `public function addSuccess(\Closure $callback): void`
* `public function addError(\Closure $callback): void`

These callbacks will be executed after the writes have been written to the database and if/when an error occurs, respectively.

### Update `\Shopware\Core\Content\Product\DataAbstractionLayer\ProductIndexer`

We update `\Shopware\Core\Content\Product\DataAbstractionLayer\ProductIndexer` to depend on `\Shopware\Core\Content\Product\DataAbstractionLayer\AbstractStockStorage` as well as `Shopware\Core\Content\Product\DataAbstractionLayer\StockUpdater`.

If `STOCK_HANDLING` is enabled then we call the `\Shopware\Core\Content\Product\DataAbstractionLayer\AbstractStockStorage::index` method with the IDs of the product which have changed.

Otherwise, we call `Shopware\Core\Content\Product\DataAbstractionLayer\StockUpdater::update`

### Deprecate `Shopware\Core\Content\Product\DataAbstractionLayer\StockUpdater`

It will be removed with 6.6.

### Introduce `Shopware\Core\Content\Product\Stock\OrderStockSubscriber`

We introduce a new subscriber which listens to the various required events and interacts with `\Shopware\Core\Content\Product\DataAbstractionLayer\AbstractStockStorage` via it's new API (`alter`).

All of Shopware's internal business rules for handling stock are located in this subscriber.

The subscriber listens to various events and calls `\Shopware\Core\Content\Product\DataAbstractionLayer\AbstractStockStorage::alter` with the appropriate changesets for the following scenarios:

* An order was placed (all items will have a before quantity of 0 and a new quantity reflective of the amount ordered)
* An order was cancelled (all items will have a before quantity of the amount ordered and a new quantity of 0)
* An order was reopened (all items will have a before quantity of 0 and a new quantity reflective of the amount ordered)
* An order item was added (before quantity of 0 and a new quantity reflective of the amount ordered)
* An order item was removed (before quantity of the amount ordered and a new quantity of 0)
* An order item quantity was updated (before and new quantity represent the old and new quantity)
* An order item product was changed (two changes: First: old product with before quantity of the amount ordered and a new quantity of 0. Second: new product with before quantity of 0 and a new quantity reflective of the amount ordered)

It is possible to disable Shopware's internal stock handling by setting the configuration `shopware.stock.enable_stock_management` to false.

### Introduce new core stock storage implementation.

We introduce a new implementation of `\Shopware\Core\Content\Product\DataAbstractionLayer\AbstractStockStorage` for managing the stock levels. It is responsible for incrementing/decrementing stock values based on the provided changesets.

The new APIs will directly increment and decrement the `stock` column on the `product` table rather than using `available_stock`. Therefore, the `stock` value will always be a realtime representation of the available stock. 

The `alter` method will directly update the stock values based on the given deltas in the changesets.

The new implementation solves the issue of the current slow stock calculation process which works like so:

* `stock` vs `available_stock` is the difference between orders in progress and completed orders.
* `available_stock` is calculated from the `stock` value minus open order quantities. This calculation is preformed in `Shopware\Core\Content\Product\DataAbstractionLayer\StockUpdater::updateAvailableStockAndSales`. It is slow because the `SUM` may run over millions of rows.

### Deprecate StockUpdater Filters

We will deprecate all stock update filters. They will be removed in 6.6. 

The same behaviour can be implemented with decorators.

The following classes will be deprecated:

* \Shopware\Core\Content\Product\DataAbstractionLayer\StockUpdate\AbstractStockUpdateFilter
* \Shopware\Tests\Unit\Core\Content\Product\DataAbstractionLayer\StockUpdate\TestStockUpdateFilter
* \Shopware\Commercial\MultiWarehouse\Domain\Order\ExcludeMultiWarehouseStockUpdateFilter
* \Shopware\Core\Content\Product\DataAbstractionLayer\StockUpdate\StockUpdateFilterProvider

### `ProductDefinition` updates

In Shopware version 6.6 or if the `STOCK_HANDLING` feature flag is enabled:

* The `availableStock` field is made write protected and will be updated to directly mirror the `stock` value.

We decide not to remove the `availableStock` field, simply deprecating it with no plan to remove. This is because many integrations rely on this field and it is simple for us to maintain as a mirror of `stock`.

To mirror the value we implement a new listener `AvailableStockMirrorSubscriber` for the `BeforeWriteEvent` event. It simply updates the payload, copying any `stock` value updates to the `available_stock` field.

### Update stock loading to use `AbstractStockStorage::load`

We update the various locations in Shopware where stock is loaded and augment the product with any stock information that is loaded from the stock storage.

This includes:

* \Shopware\Core\Content\Product\Subscriber\ProductSubscriber::salesChannelLoaded
* \Shopware\Core\Content\Product\SalesChannel\Detail\AvailableCombinationLoader::load

Pseudocode for setting the values on the product looks like:

```php
$product->setStock($stock->stock);
$product->setAvailable($stock->available);

// optional values
$product->setMinPurchase($stock->minPurchase ?? $product->get('minPurchase'));
$product->setMaxPurchase($stock->maxPurchase ?? $product->get('maxPurchase'));
$product->setIsCloseout($stock->isCloseout ?? $product->get('isCloseout'));

// really flexible for projects
$product->addExtension('stock_data', $stock);
```

However, in order to support this API, we must update `\Shopware\Core\Content\Product\SalesChannel\Detail\AvailableCombinationLoader::load` because it currently does not pass along the `SalesChannelContext` which is necessary for `AbstractStockStorage::load`.

Therefore, we deprecate `load` in `AbstractAvailableCombinationLoader` for 6.6 and introduce:

`public function loadCombinations(string $productId, SalesChannelContext $salesChannelContext): AvailableCombinationResult`.

It is introduced as not abstract and throws a deprecation error if called (eg when the method is not implemented in concrete implementations) in 6.6, otherwise it forwards to `load`. It will be made abstract in 6.6.

`AvailableCombinationLoader` implements the new `loadCombinations` method and `load` is deprecated for 6.6.

Finally, `ProductConfiguratorLoader` is updated to call `loadCombinations` instead of `load`.

### Stock changing scenarios

The following table contains all the scenarios that should trigger stock changes. All implementations of `AbstractStockStorage` should be able to handler these scenarios.

| Scenario                                     | Items Before                                    | Items After                                     | Before Stock Values                               | After Stock Values                               | Diff                             |
|----------------------------------------------|-------------------------------------------------|-------------------------------------------------|---------------------------------------------------|--------------------------------------------------|----------------------------------|
| Order placed                                 | N/A                                             | Product 1: 10<br/>Product 2: 5                  | Product 1: 100<br/>Product 2: 55                  | Product 1: 90<br/>Product 2: 50                  | Product 1: -10<br/>Product 2: -5 |
| Order cancelled                              | Product 1: 10<br/>Product 2: 5                  | Product 1: 10<br/>Product 2: 5                  | Product 1: 90<br/>Product 2: 50                   | Product 1: 100<br/>Product 2: 55                 | Product 1: +10<br/>Product 2: +5 |
| Cancelled Order -> Open                      | Product 1: 10<br/>Product 2: 5                  | Product 1: 10<br/>Product 2: 5                  | Product 1: 100<br/>Product 2: 55                  | Product 1: 90<br/>Product 2: 50                  | Product 1: -10<br/>Product 2: -5 |
| Line Item Added -> Product 3                 | Product 1: 10<br/>Product 2: 5                  | Product 1: 10<br/>Product 2: 8<br/>Product 3: 1 | Product 1: 90<br/>Product 2: 50<br/>Product 3: 5  | Product 1: 90<br/>Product 2: 47<br/>Product 3: 4 | Product 2: -3<br/>Product 3: -1  |
| Line Item Removed -> Product 3               | Product 1: 10<br/>Product 2: 8<br/>Product 3: 1 | Product 1: 10<br/>Product 2: 8                  | Product 1: 90<br/>Product 2: 47<br/>Product 3: 4  | Product 1: 90<br/>Product 2: 50<br/>Product 3: 5 | Product 3: +1                    |
| Line Item Updated -> Product 2 qty increased | Product 1: 10<br/>Product 2: 5                  | Product 1: 10<br/>Product 2: 8                  | Product 1: 90<br/>Product 2: 50                   | Product 1: 90<br/>Product 2: 47                  | Product 2: -3                    |
| Line Item Updated -> Product 2 qty decreased | Product 1: 10<br/>Product 2: 5                  | Product 1: 10<br/>Product 2: 1                  | Product 1: 90<br/>Product 2: 50                   | Product 1: 90<br/>Product 2: 54                  | Product 2: +4                    |
| Line Item Updated -> P2 changed to P3        | Product 1: 10<br/>Product 2: 5                  | Product 1: 10<br/>Product 3: 5                  | Product 1: 90<br/>Product 2: 50<br/>Product 3: 10 | Product 1: 90<br/>Product 2: 55<br/>Product 3: 5 | Product 2: +5<br/>Product 3: -5  |
| Non cancelled order deleted                  | Product 1: 10<br/>Product 2: 5                  | Product 1: 10<br/>Product 2: 5                  | Product 1: 90<br/>Product 2: 50                   | Product 1: 100<br/>Product 2: 55                 | Product 1: +10<br/>Product 2: +5 |

It is the role of `Shopware\Core\Content\Product\Stock\OrderStockSubscriber` to listen to the required shopware events for these scenarios and then interact with the stock storage implementation.

1. Order placed: The product stock should be reduced by the order line item qties. (`BeforeWriteEvent` -> No items will exist pre insertion, so we know it's a decrement operation)
2. Order cancelled: The product stock should be increased by the order line item qties. (`StateMachineTransitionEvent -> $event->getToPlace()->getTechnicalName() === OrderStates::STATE_CANCELLED`)
3. Order reopened: The product stock should be reduced by the order line item qties. (`StateMachineTransitionEvent ->  $event->getFromPlace()->getTechnicalName() === OrderStates::STATE_CANCELLED`)
4. Order item added: The product stock should be reduced by the new order line item qty. (`BeforeWriteEvent` -> filter for order line item writes and diff old and new state)
5. Order item removed (Status: Any non cancelled): The product stock is increased by the old order line item qty. (`BeforeWriteEvent` -> filter for order line item writes and diff old and new state)
6. Order item qty increased (Status: Any non cancelled): The product stock should be decreased by the difference between the old and new qty. (`BeforeWriteEvent` -> filter for order line item writes and diff old and new state)
7. Order item qty decreased (Status: Any non cancelled): The product stock should be increased by the difference between the old and new qty. (`BeforeWriteEvent` -> filter for order line item writes and diff old and new state)
8. Order item product changed (Status: Any non cancelled): The old product stock should be increased by the old qty. The new product stock should be decreased by the new qty. (`BeforeWriteEvent` -> filter for order line item writes and diff old and new state)

## Consequences

* By creating an abstract class, we can maintain a consistent interface for stock updating while allowing for different implementations.
* New inventory management strategies can be easily added by creating new concrete classes that extend `AbstractStockStorage`.
* Developers working with the inventory management system can be confident that any concrete implementation of the `AbstractStockStorage` will provide the required methods for handling stock updates.
* Developers wanting to completely remove and rewrite the inventory management logic can completely disable the `OrderStockSubscriber` and implement their own solution.
