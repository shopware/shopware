---
title: Added sold quantity metric
issue: NEXT-10765
author: Lennart Tinkloh
author_email: l.tinkloh@shopware.com 
author_github: lernhart
---
# Core
* Added `Shopware\Core\Migration\Migration1600156989AddProductSalesField` to add `sales` field to `product` table
* Added new write protected int field `sales` to `Shopware\Core\Content\Product\ProductDefinition`
* Added new property `sales` and corresponding getter / setter in `Shopware/Core/Content/Product/ProductEntity`
* Changed `Shopware/Core/Content/Product/DataAbstractionLayer/StockUpdater` to additionally update sold quantity of products
___
