---
title: Fix main variant preselection if displayParent is active
issue: #13208
author: Alex Canals
author_email: alex.canals@novu.ch
author_github: @Alex--C
---
# Core
* Changed `\Shopware\Core\Content\Product\SalesChannel\Detail\ProductDetailRoute::checkVariantListingConfig()` to return the selected main variant when `displayParent` is active.
