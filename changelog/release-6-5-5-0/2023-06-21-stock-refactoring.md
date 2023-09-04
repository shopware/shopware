---
title: Refactor stock management
issue: NEXT-12479
---
# Core
* Changed `Shopware\Core\Checkout\Cart\Order\RecalculationService` to roll back the `orderStateId` after order conversion.
* Deprecated `Shopware\Core\Content\Product\DataAbstractionLayer\StockUpdater`. It will be removed in 6.6. Furthermore, if you opt into the new stock handling method with the `STOCK_HANDLING` feature flag, the subscriber will no longer execute.
* Added `\Shopware\Core\Content\Product\Stock\AbstractStockStorage` to handle stock alterations and stock loading.
* Added `\Shopware\Core\Content\Product\Stock\StockStorage` as the default implementation of `\Shopware\Core\Content\Product\Stock\AbstractStockStorage`. It persists stock alterations to the product table, using the `product.stock` field.
* Added `\Shopware\Core\Content\Product\SalesChannel\Detail\AbstractAvailableCombinationLoader::loadCombinations()` with a default implementation which proxies to `load`. It must be implemented in 6.6, as the `load` method will be removed. The signature is updated to `string $productId, SalesChannelContext $salesChannelContext` instead of `string $productId, Context $context, string $salesChannelId`.
* Deprecated `\Shopware\Core\Content\Product\SalesChannel\Detail\AbstractAvailableCombinationLoader::load()`. It will be removed in 6.6. Use `loadCombinations` instead.
* Deprecated `\Shopware\Core\Content\Product\SalesChannel\Detail\AvailableCombinationLoader::load()`. It will be removed in 6.6. Use `loadCombinations` instead.
* Added `\Shopware\Core\Content\Product\SalesChannel\Detail\AvailableCombinationLoader::loadCombinations()`. It is the same as `load` with the update method signature.
* Added `\Shopware\Core\Content\Product\Stock\LoadProductStockSubscriber` to allow augmenting stock data when loading products via `\Shopware\Core\Content\Product\Stock\AbstractStockStorage::load`. By decorating `\Shopware\Core\Content\Product\Stock\AbstractStockStorage::load` you can easily load your stock data from a different source.
* Added the feature flag `STOCK_HANDLING` to opt into the new stock handling implementation.
* Deprecated writing to the `product.availableStock` field. It will become write protected in 6.6. There are no plans to remove it. Opt in early with the `STOCK_HANDLING` feature flag.
* Changed the `product.stock` field to represent the realtime stock of a product. When a product is ordered, the stock value is instantly decremented. When an order is cancelled, the stock value is instantly incremented and so on. This will become the default behaviour in 6.6 but for now can be activated via the `STOCK_HANDLING` feature flag.
* Added new configuration setting `stock.enable_stock_management`. The default value is true. 
* Added `\Shopware\Core\Content\Product\Stock\OrderStockSubscriber`. This subscriber is responsible for communicating with `\Shopware\Core\Content\Product\Stock\AbstractStockStorage` and issuing stock alteration commands whenever an order is created or transitioned through the various states. The subscriber is only enabled if the configuration setting `stock.enable_stock_management` is enabled AND the `STOCK_HANDLING` feature flag is enabled. This will be the default behaviour in 6.6.
* Added `\Shopware\Core\Content\Product\Stock\AvailableStockMirrorSubscriber`. This subscriber is responsible for updating the `product.availableStock` field whenever the `product.stock` field is written. This mirror is useful in case you have some integration which relies on this field which cannot be updated. The subscriber is only enabled if the `STOCK_HANDLING` feature flag is enabled. This will be the default behaviour in 6.6.
* Changed `\Shopware\Core\Content\Product\DataAbstractionLayer\ProductIndexer`. It now depends on `\Shopware\Core\Content\Product\Stock\AbstractStockStorage`. If the `STOCK_HANDLING` feature flag is not enabled, `Shopware\Core\Content\Product\DataAbstractionLayer\StockUpdater` is used to recalculate stock availability for the updated and indexed products. If it is not enabled, `\Shopware\Core\Content\Product\Stock\AbstractStockStorage` is used to index the updated products, which means the stock availability is recalculated.
* Added `\Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWriteEvent`. This event allows you to hook in to the process of writing an entity. This includes, creating, updating and deleting entities. You have the possibility to execute code before and after the entity is written via the success and error callbacks. You can call the `addSuccess` or `addError` methods with any PHP callable.
* Added `\Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeleteEvent`. This event allows you to hook in to the process of removing an entity. You have the possibility to execute code before and after the entity is removed via the success and error callbacks. You can call the `addSuccess` or `addError` methods with a Closure.
* Deprecated `\Shopware\Core\Framework\DataAbstractionLayer\Event\BeforeDeleteEvent`. It will be removed in 6.6. Use `\Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeleteEvent` instead, it has the same API.
* Changed `\Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityWriteGateway` to dispatch the `\Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWriteEvent` event, whenever entities are written (created, updated, deleted) and execute the added success and error callbacks when the operation succeeds or fails, respectively.
___
# Next Major Version Changes

## New stock handling implementation is now the default

The `product.stock` field is now the primary source for real time product stock values. However, `product.availableStock` is a direct mirror of `product.stock` and is updated whenever `product.stock` is updated via the DAL.

A database migration `\Shopware\Core\Migration\V6_6\Migration1691662140MigrateAvailableStock` takes care of copying the `available_stock` field to the `stock` field.

## New configuration values

* `stock.enable_stock_management` - Default `true`. This can be used to completely disable Shopware's stock handling. If disabled, product stock will be not be updated as orders are created and transitioned through the various states.

## Removed `\Shopware\Core\Content\Product\DataAbstractionLayer\StockUpdater`

The listener was replaced with a new listener `\Shopware\Core\Content\Product\Stock\OrderStockSubscriber` which handles subscribing to the various order events and interfaces with the stock storage `\Shopware\Core\Content\Product\Stock\AbstractStockStorage` to write the stock alterations.

## Removed `\Shopware\Core\Content\Product\SalesChannel\Detail\AbstractAvailableCombinationLoader::load()` && `\Shopware\Core\Content\Product\SalesChannel\Detail\AvailableCombinationLoader::load()`

These methods are removed and superseded by `loadCombinations` which has a different method signature.

From:

```php
public function load(string $productId, Context $context, string $salesChannelId)
```

To:

```php
public function loadCombinations(string $productId, SalesChannelContext $salesChannelContext): AvailableCombinationResult
```

The `loadCombinations` method has been made abstract so it must be implemented. The `SalesChannelContext` instance, contains the data which was previously in the defined on the `load` method. 

`$salesChannelId` can be replaced with `$salesChannelContext->getSalesChannel()->getId()`.

`$context` can be replaced with `$salesChannelContext->getContext()`.

## Writing to `product.availableStock` field is now not possible

The field is write protected. Use the `product.stock` to write new stock levels. 

## Reading product stock

The `product.stock` should be used to read the current stock level. When building new extensions which need to query the stock of a product, use this field. Not the `product.availableStock` field.

## Removed `\Shopware\Core\Framework\DataAbstractionLayer\Event\BeforeDeleteEvent`

It is replaced by `\Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWriteEvent` with the same API.

You should use `\Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWriteEvent` instead, only the class name changed.

___
# Upgrade Information

Shopware 6.5 introduces a new more flexible stock management system. Please see the [ADR](../../adr/2023-05-15-stock-api.md) for a more detailed description of the why & how.

It is disabled by default, but you can opt in to the new system by enabling the `STOCK_HANDLING` feature flag.

When you opt in and Shopware is your main source of truth for stock values, you might want to migrate the available_stock field to the `stock` field so that the `stock` value takes into account open orders.

You can use the following SQL:

```sql
UPDATE product SET stock = available_stock WHERE stock != available_stock
```

Bear in mind that this query might take a long time, so you could do it in a loop with a limit. See `\Shopware\Core\Migration\V6_6\Migration1691662140MigrateAvailableStock` for inspiration.

## If you have decorated `StockUpdater::update`

If you have previously decorated `\Shopware\Core\Content\Product\DataAbstractionLayer\StockUpdater` you must refactor your code. Depending on what you want to accomplish you have two options:

* You have the possibility to decorate the `\Shopware\Core\Content\Product\Stock\AbstractStockStorage::alter` method. This method is called by `\Shopware\Core\Content\Product\Stock\OrderStockSubscriber` as orders are created and transitioned through the various states. By decorating you can persist the stock deltas to a different storage. For example, an API.
* You can disable `\Shopware\Core\Content\Product\Stock\OrderStockSubscriber` entirely with the `stock.enable_stock_management` configuration setting, and implement your own subscriber to listen to order events. You can use Shopware's stock storage `\Shopware\Core\Content\Product\Stock\AbstractStockStorage`, or implement your own entirely.

## Decorating `\Shopware\Core\Content\Product\SalesChannel\Detail\AbstractAvailableCombinationLoader::load()` && `\Shopware\Core\Content\Product\SalesChannel\Detail\AvailableCombinationLoader::load()`

If you decorated `\Shopware\Core\Content\Product\SalesChannel\Detail\AvailableCombinationLoader::load()` you should instead decorate `\Shopware\Core\Content\Product\SalesChannel\Detail\AvailableCombinationLoader::loadCombinations()`. The method does the same, but the signature is slightly modified.

If you extended `\Shopware\Core\Content\Product\SalesChannel\Detail\AbstractAvailableCombinationLoader`, you should implement the new `loadCombinations` instead of `load` method.

Before:

```php
use Shopware\Core\Content\Product\SalesChannel\Detail\AbstractAvailableCombinationLoader;
use Shopware\Core\Content\Product\SalesChannel\Detail\AvailableCombinationResult;
use Shopware\Core\Framework\Context;

class AvailableCombinationLoaderDecorator extends AbstractAvailableCombinationLoader
{
    public function load(string $productId, Context $context, string $salesChannelId): AvailableCombinationResult
    {
    
    }
}
```

After:

```php
use Shopware\Core\Content\Product\SalesChannel\Detail\AbstractAvailableCombinationLoader;
use Shopware\Core\Content\Product\SalesChannel\Detail\AvailableCombinationResult;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class AvailableCombinationLoaderDecorator extends AbstractAvailableCombinationLoader
{
    public function loadCombinations(string $productId, SalesChannelContext $salesChannelContext): AvailableCombinationResult
    {
        $context = $salesChannelContext->getContext();
        $salesChannelId = $salesChannelContext->getSalesChannelId();
    }
}
```

Similarly, if you consume `\Shopware\Core\Content\Product\SalesChannel\Detail\AbstractAvailableCombinationLoader` then you will need to adjust your code, to pass in `\Shopware\Core\System\SalesChannel\SalesChannelContext`.

Before:

```php
use Shopware\Core\Content\Product\SalesChannel\Detail\AbstractAvailableCombinationLoader;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class SomeService
{
     public function __construct(private AbstractAvailableCombinationLoader $availableCombinationLoader)
     {}
     
     public function __invoke(SalesChannelContext $salesChannelContext): void
     {
        $this->availableCombinationLoader->load('some-product-id', $salesChannelContext->getContext(), $salesChannelContext->getSalesChannelId());
     }
}
```

After:

```php
use Shopware\Core\Content\Product\SalesChannel\Detail\AbstractAvailableCombinationLoader;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class SomeService
{
     public function __construct(private AbstractAvailableCombinationLoader $availableCombinationLoader)
     {}
     
     public function __invoke(SalesChannelContext $salesChannelContext): void
     {
        $this->availableCombinationLoader->loadCombinations('some-product-id', $salesChannelContext);
     }
}
```

## Loading stock information from a different source

If Shopware is not the source of truth for your stock data, you can decorate `\Shopware\Core\Content\Product\Stock\AbstractStockStorage` and implement the `load` method. When products are loaded in Shopware the `load` method will be invoked with the loaded product ID's. You can return a collection of `\Shopware\Core\Content\Product\Stock\StockData` objects, each representing a products stock level and configuration. This data will be merged with the Shopware stock levels and configuration from the product. Any data specified will override the product's data.

For example, you can use an API to fetch the stock data:

```php
//<plugin root>/src/Service/StockStorageDecorator.php
<?php

namespace Swag\Example\Service;

use Shopware\Core\Content\Product\Stock\AbstractStockStorage;
use Shopware\Core\Content\Product\Stock\StockData;
use Shopware\Core\Content\Product\Stock\StockDataCollection;
use Shopware\Core\Content\Product\Stock\StockLoadRequest;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class StockStorageDecorator extends AbstractStockStorage
{
    public function __construct(private AbstractStockStorage $decorated)
    {
    }

    public function getDecorated(): AbstractStockStorage
    {
        return $this->decorated;
    }

    public function load(StockLoadRequest $stockRequest, SalesChannelContext $context): StockDataCollection
    {
        $productsIds = $stockRequest->productIds;

        //use $productIds to make an API request to get stock data
        //$result would come from the api response
        $result = ['product-1' => 5, 'product-2' => 10];

        return new StockDataCollection(
            array_map(function (string $productId, int $stock) {
                return new StockData($productId, $stock, true);
            }, array_keys($result), $result)
        );
    }

    public function alter(array $changes, Context $context): void
    {
        $this->decorated->alter($changes, $context);
    }

    public function index(array $productIds, Context $context): void
    {
        $this->decorated->index($productIds, $context);
    }
}
```

```xml
<!--<plugin root>/src/Resources/config/services.xml-->
<services>
    <service id="Swag\Example\Service\StockStorageDecorator" decorates="Shopware\Core\Content\Product\Stock\StockStorage">
        <argument type="service" id="Swag\Example\Service\StockStorageDecorator.inner" />
    </service>

</services>
```

## Reading and writing the current stock level

The `product.stock` field is now a realtime representation of the product stock. When writing new extensions which need to query the stock of a product, use this field. Not the `product.availableStock` field.

Before:

```php
/** \Shopware\Core\Content\Product\ProductEntity $product */
$stock = $product->getAvailableStock();
```

After:

```php
/** \Shopware\Core\Content\Product\ProductEntity $product */
$stock = $product->getStock();
```

## Writing the current stock level

If you write to the `product.availableStock` field, you should instead write to the `product.stock` field. However, there are no plans to remove the `product.availableStock` field.

Before:

```php

$this->productRepository->update(
    [
        [
            'id' => $productId,
            'availableStock' => $newStockValue
        ]
    ],
    $context
);
```

After:

```php

$this->productRepository->update(
    [
        [
            'id' => $productId,
            'stock' => $newStockValue
        ]
    ],
    $context
);
```

## Disabling Shopware's stock management system

You can disable `\Shopware\Core\Content\Product\Stock\OrderStockSubscriber` entirely with the `stock.enable_stock_management` configuration setting.

## Implementing your own stock storage

Similar to the example above "Loading stock information from a different source" you can update a different database table or service, or implement custom inventory systems such as multi warehouse by decorating the `alter` method. 
This method is triggered with an array of `StockAlteration`'s. Which contain the Product & Line Item ID's, the old quantity and the new quantity. 

This method is triggered whenever an order is created or transitioned through the various states.

## Listening to entity delete events

The `BeforeDeleteEvent` has been renamed to `\Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeleteEvent`. Please update your usages:

Before:

```php
/**
 * @return array<string, string>
 */
public static function getSubscribedEvents(): array
{
    return [
        \Shopware\Core\Framework\DataAbstractionLayer\Event\BeforeDeleteEvent::class => 'onBeforeDelete',
    ];
}
```

After:

```php
/**
 * @return array<string, string>
 */
public static function getSubscribedEvents(): array
{
    return [
        \Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeleteEvent::class => 'onBeforeDelete',
    ];
}
```
