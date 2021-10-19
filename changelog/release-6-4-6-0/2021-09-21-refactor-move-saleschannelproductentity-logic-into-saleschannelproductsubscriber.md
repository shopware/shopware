---
title: Move SalesChannelProductEntity logic into sales_channel.product.loaded event
issue: NEXT-17472
author: Max
author_email: max@swk-web.com
author_github: @aragon999
---
# Core
* Removed class `Shopware\Core\Content\Product\SalesChannel\SalesChannelProductSubscriber` and merge the logic into `Shopware\Core\Content\Product\Subscriber\ProductSubscriber`, since it will be only computed for `Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity`.
* Added class `Shopware\Core\Content\Product\ProductVariationBuilder` to build variations of the product.
* Added class `Shopware\Core\Content\Product\SalesChannelProductBuilder` to build different properties which are needed for the `SalesChannelProductEntity`.
* Added class `Shopware\Core\Content\Product\IsNewDetector`.
* Added class `Shopware\Core\Content\Product\PropertyGroupSorter`.
* Added class `Shopware\Core\Content\Product\ProductMaxPurchaseCalculator`.
