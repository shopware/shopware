---
title: Fixed Category CMS Page error when dynamic group is not selected
issue: NEXT-11034
---
# Core
*  Added condition check `$category->getProductStreamId() !== null` in `\Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingRoute::load` to prevent load non-existent product stream.
