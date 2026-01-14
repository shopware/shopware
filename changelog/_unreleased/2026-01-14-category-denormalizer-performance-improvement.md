---
title: Performance improvement for category denormalizer
issue: #14332
---
# Core
* Changed `\Shopware\Core\Content\Product\DataAbstractionLayer\ProductCategoryDenormalizer` to to add a filter for indexed columns, thus preventing a costly full table scan, improving the performance on large data sets from 3s to under 1ms.
