---
title: Add products per page in listing setting
issue: NEXT-11285
author_github: @Dominik28111
---
# Core
* Added `Core\Migration\Migration1603179023AddDefaultSettingConfigValueForProductListingPerPage` to add default value for system config option `core.listing.productsPerPage`.
* Changed method `handleListingRequest()` in `Core\Content\Product\SalesChannel\Listing\ProductListingFeaturesSubscriber` to use salesChannelContext in `handlePagination()` to get the number of shown products per page. 
