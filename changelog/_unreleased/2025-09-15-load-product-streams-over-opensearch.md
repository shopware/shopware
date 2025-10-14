---
title: Load product streams over opensearch
issue: 12411
---
# Core
* Changed `\Shopware\Core\Content\Product\DataAbstractionLayer\ProductStreamUpdater::getCriteria` to optimize product stream loading by using OpenSearch for filtering products in product streams. 
* Changed `\Shopware\Core\Content\Product\DataAbstractionLayer\ProductStreamUpdater::handle` to reduce number of unnecessary delete and insert operations when updating product streams.
* Changed `\Shopware\Core\Content\Product\Cms\ProductSlider\ProductStreamProcessor::collectByProductStream` to load product streams using OpenSearch for better performance.
* Changed `\Shopware\Core\Content\Product\Cms\ProductSlider\ProductStreamProcessor::addRandomSort` to ensure random sorting works correctly with OpenSearch.