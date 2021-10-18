---
title: Move SalesChannelProductEntity logic into sales_channel.product.loaded event
issue: NEXT-17472
author: Max
author_email: max@swk-web.com
author_github: @aragon999
---
# Core
* Move the logic of sorting product properties and cheapest price computation from the event `product.loaded` to the event `sales_channel.product.loaded`, since it will be only computed for `Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity`
* Remove the `Shopware\Core\Content\Product\SalesChannel\SalesChannelProductSubscriber` and merge the logic into `Shopware\Core\Content\Product\Subscriber\ProductSubscriber`
* Add `Shopware\Core\Content\Product\ProductVariationBuilder` to build variations of the product
* Add `Shopware\Core\Content\Product\SalesChannelProductBuilder` to build different properties which are needed for the `SalesChannelProductEntity`
