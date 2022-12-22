---
title: Add possibility to filter products handled by stock updater
issue: NEXT-23356
author_github: @Dominik28111
---
# Core
* Added abstract class `Shopware\Core\Content\Product\DataAbstractionLayer\StockUpdate\AbstractProductStockUpdater`.
* Added class `Shopware\Core\Content\Product\DataAbstractionLayer\StockUpdate\StockUpdateFilterProvider`.
* Changed method `Shopware\Core\Content\Product\DataAbstractionLayer\StockUpdater::update()` to use `StockUpdateFilterHandler`.
