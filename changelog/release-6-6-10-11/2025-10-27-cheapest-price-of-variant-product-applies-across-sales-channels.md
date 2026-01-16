---
title: Cheapest price of variant product applies across sales channels
issue: 8577
---
# Core
* Changed `Shopware\Core\Content\Product\DataAbstractionLayer\CheapestPriceUpdater` to add the sales_channel_ids for each rule in cheapest_price_container, so we can correctly solve the available variant.
