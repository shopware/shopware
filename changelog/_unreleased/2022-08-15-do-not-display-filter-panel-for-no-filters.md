---
title: Do not display filter panel for no filters
issue: NA
author: Max
author_email: max@swk-web.com
author_github: @aragon999
---
# Core
* Remove `price`, `manufacturer`, `rating`, `shipping-free`, `properties`, `options` and `configurators` aggregations of the `Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingRouteResponse` if no filterable items are found in order to hide the filter panel in the storefront
