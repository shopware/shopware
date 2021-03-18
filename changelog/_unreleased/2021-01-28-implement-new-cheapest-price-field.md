---
title: Implement new cheapest price field
issue: NEXT-12169
author: OliverSkroblin
author_email: o.skroblin@shopware.com 
author_github: OliverSkroblin
---
# Core
* Added `\Shopware\Core\Content\Product\DataAbstractionLayer\CheapestPrice\CalculatedCheapestPrice`
* Added `\Shopware\Core\Content\Product\DataAbstractionLayer\CheapestPrice\CheapestPrice`
* Added `\Shopware\Core\Content\Product\DataAbstractionLayer\CheapestPrice\CheapestPriceAccessorBuilder`
* Added `\Shopware\Core\Content\Product\DataAbstractionLayer\CheapestPrice\CheapestPriceContainer`
* Added `\Shopware\Core\Content\Product\DataAbstractionLayer\CheapestPrice\CheapestPriceField`
* Added `\Shopware\Core\Content\Product\DataAbstractionLayer\AbstractCheapestPriceQuantitySelector`
* Added `\Shopware\Core\Content\Product\DataAbstractionLayer\CheapestPriceUpdater` 
* Added `\Shopware\Core\Content\Product\Aggregate\ProductPrice\ProductPriceCollection::filterByRuleId` 
* Added `\Shopware\Core\Content\Product\Aggregate\ProductPrice\ProductPriceCollection::sortByQuantity` 
* Added `\Shopware\Core\Content\Product\SalesChannel\Price\AbstractProductPriceCalculator` 
* Added `\Shopware\Core\Content\Product\SalesChannel\Price\ReferencePriceDto` 
* Added `\Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity::$calculatedCheapestPrice`
* Added `\Shopware\Core\Content\Product\ProductEntity::$cheapestPrice`, which contains the cheapest available price
* Added `\Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\PHPUnserializeFieldSerializer`, which can be used for php serialized values
* Added `\Shopware\Core\Framework\DataAbstractionLayer\VersionManager::DISABLE_AUDIT_LOG`, which allows to disable the audit log be written
* Added `\Shopware\Core\System\SalesChannel\SalesChannelContext::getCurrencyId` 
* Deprecated `\Shopware\Core\Content\Product\DataAbstractionLayer\Indexing\ListingPriceUpdater`, will be removed
* Deprecated `\Shopware\Core\Content\Product\SalesChannel\Price\ProductPriceDefinitionBuilderInterface`, use `AbstractProductPriceCalculator` instead
* Deprecated `\Shopware\Core\Content\Product\SalesChannel\Price\ProductPriceDefinitions`, will be removed
* Deprecated `\Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity::$calculatedListingPrice`, use `calculatedCheapestPrice` instead
* Deprecated `\Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity::getCalculatedListingPrice`, use `calculatedCheapestPrice` instead
* Deprecated `\Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity::setCalculatedListingPrice`, use `calculatedCheapestPrice` instead
* Deprecated `\Shopware\Core\Content\Product\ProductEntity::$grouped`, will be removed
* Deprecated `\Shopware\Core\Content\Product\ProductEntity::setGrouped`, will be removed
* Deprecated `\Shopware\Core\Content\Product\ProductEntity::isGrouped`, will be removed
* Deprecated `\Shopware\Core\Content\Product\ProductEntity::$listingPrices`, use `cheapestPrice` instead
* Deprecated `\Shopware\Core\Content\Product\ProductEntity::getListingPrices`, use `cheapestPrice` instead
* Deprecated `\Shopware\Core\Content\Product\ProductEntity::setListingPrices`, use `cheapestPrice` instead
