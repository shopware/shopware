---
title: Fix determination of product id and name of GA plugin
issue: NEXT-17278
author: Max
author_email: max@swk-web.com
author_github: @aragon999
---
# Storefront
* Changed the selector of the Google Analytics `ViewItemEvent` (`app/storefront/src/plugin/google-analytics/events/view-item.event.js`), for the product id to `[itemtype="https://schema.org/Product"] meta[itemprop="productID"]` and for the product name to `[itemtype="https://schema.org/Product"] [itemprop="name"]` to also work for soldout products
* Removed `findProductId` of the Google Analytics `ViewItemEvent`, use the value of `[itemtype="https://schema.org/Product"] meta[itemprop="productID"]` if needed
