---
title: Refactor CheapestPrice indexing
issue: NEXT-16151
flag: FEATURE_NEXT_16151
---
# Core
* Changed `\Shopware\Core\Content\Product\DataAbstractionLayer\CheapestPriceUpdater` and `\Shopware\Core\Content\Product\DataAbstractionLayer\CheapestPrice\CheapestPriceContainer` to add default product price only once to CheapestPriceStruct.
* Added `CheapestPriceField` to `\Shopware\Core\Content\Product\SalesChannel\SalesChannelProductDefinition` and the according getters and setters to `\Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity`.
* Deprecated `getCheapestPrice()`, `setCheapestPrice()`, `getCheapestPriceContainer()` and `setCheapestPriceContainer()` methods on `\Shopware\Core\Content\Product\ProductEntity`, those methods will only be provided by the `SalesChannelProductEntity`.
* Changed `\Shopware\Core\Content\Product\Subscriber\ProductSubscriber` to resolve the CheapestPrice only for `SalesChannelProductEntity`.
___
# Upgrade Information
## Moved CheapestPrice to `SalesChannelProductEntity`
The CheapestPrice will only be resolved in SalesChannelContext, thus it moved from the basic `ProductEntity` to the `SalesChannelProductEntity`.
If you rely on the CheapestPrice props of the ProductEntity in your plugin, make sure that you are in a SalesChannelContext and use the `sales_channel.product.repository` instead of the `product.repository`
### Before
```
private EntityRepositoryInterface $productRepository;

public function custom(SalesChannelContext $context): void
{
    $products = $this->productRepository->search(new Criteria(), $context->getContext());
    /** @var ProductEntity $product */
    foreach ($products as $product) {
        $cheapestPrice = $product->getCheapestPrice();
        // do stuff with $cheapestPrice
    }
}
```
### After 
```
private SalesChannelRepositoryInterface $salesChannelProductRepository;

public function custom(SalesChannelContext $context): void
{
    $products = $this->salesChannelProductRepository->search(new Criteria(), $context);
    /** @var SalesChannelProductEntity $product */
    foreach ($products as $product) {
        $cheapestPrice = $product->getCheapestPrice();
        // do stuff with $cheapestPrice
    }
}
```
