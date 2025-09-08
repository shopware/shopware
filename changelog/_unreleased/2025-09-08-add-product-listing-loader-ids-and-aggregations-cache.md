---
title: Add ProductListingLoader ids and aggregations cache
author: Benjamin Wittwer
author_email: Discord.Benjamin@web.de
author_github: gecolay
---
# Core
* Added `Shopware\Core\Content\Product\Extension\ResolveListingAggregationsExtension` to allow overriding the product listing aggregations result
* Changed `Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingLoader` to cache the ids and aggregations results per criteria & sales channel context hash to improve performance
