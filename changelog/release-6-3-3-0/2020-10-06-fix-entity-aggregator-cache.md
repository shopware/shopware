---
title: Fix entity aggregator cache
issue: NEXT-11269
author: Oliver Skroblin
author_email: o.skroblin@shopware.com 
author_github: @OliverSkroblin
---
# Core
* Removed criteria.extensions from aggregation cache key in `\Shopware\Core\Framework\DataAbstractionLayer\Cache\EntityCacheKeyGenerator::getAggregationCacheKey`
* Changed injected repository of `\Shopware\Core\Checkout\Customer\DataAbstractionLayer\CustomerIndexer` to index customers instead of products  
