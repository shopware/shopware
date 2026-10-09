---
title: Update dynamic product groups after category assignment
issue: 6774
---
# Core
* Changed `Shopware\Core\Content\Product\DataAbstractionLayer\ProductIndexer::handle` to run the product stream and many-to-many id field updaters after the category denormalizer and the other field updaters, so dynamic product groups filtering by category also match products assigned on the category.
