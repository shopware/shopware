---
title: Read runtime fields
issue: NEXT-24403
author: Oliver Skroblin
author_email: o.skroblin@shopware.com
---
# Core
* Added possibility to request runtime fields via DAL when using the partial data loading
* Added field dependency for runtime fields in the sales channel product definition
  * `product::isNew` depends now on `product::releaseDate`
  * all calculated price fields depends on `product::taxId`, `product::unitId`, `product::referenceUnit`, `product::purchaseUnit`
* Changed signature of `AbstractProductMaxPurchaseCalculator` to use `Entity` instead of `ProductEntity`
* Changed signature of `PropertyGroupSorter` to use `EntityCollection` instead of `PropertyGroupOptionCollection`
___
# Upgrade Information
## Signature change of property group sorter and max purchase calculator
You have to change the signature of your `AbstractProductMaxPurchaseCalculator` implementation as follows:
```php
// before
abstract public function calculate(SalesChannelProductEntity $product, SalesChannelContext $context): int;

// after
abstract public function calculate(Entity $product, SalesChannelContext $context): int;
```

You have to change the signature of your `PropertyGroupSorter` implementation as follows:
```php
// before
abstract public function sort(PropertyGroupOptionCollection $groupOptionCollection): PropertyGroupCollection;

// after
abstract public function sort(EntityCollection $options): PropertyGroupCollection;
```