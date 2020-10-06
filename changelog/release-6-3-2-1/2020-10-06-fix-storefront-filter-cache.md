---
title: Fix storefront filter cache
issue: NEXT-11273
author: Oliver Skroblin
author_email: o.skroblin@shopware.com 
author_github: @OliverSkroblin
---
# Storefront
* Removed `sortings` extension from criteria in `\Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingFeaturesSubscriber::handleSorting` 
