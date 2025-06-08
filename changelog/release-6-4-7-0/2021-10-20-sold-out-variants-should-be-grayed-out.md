---
title: Sold out variants should be grayed out
issue: NEXT-15280
author_github: @Dominik28111
---
# Core
* Deprecated method `Shopware\Core\Content\Product\SalesChannel\Detail\AvailableCombinationResult::addCombination()`, parameter `$available`will be mandatory with 6.5.0.
* Changed method `Shopware\Core\Content\Product\SalesChannel\Detail\AvailableCombinationLoader::load()` to load stock and closeout of products to check whether it's available or not.
* Added method `Shopware\Core\Content\Product\SalesChannel\Detail\AvailableCombinationResult::isAvailable()`.
* Added property `$combinationDetails` to `Shopware\Core\Content\Product\SalesChannel\Detail\AvailableCombinationResult::isAvailable()`.
