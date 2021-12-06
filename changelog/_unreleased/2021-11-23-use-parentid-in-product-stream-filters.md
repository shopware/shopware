---
title: Use parentId in product stream filters
issue: NEXT-18154
author: d.neustadt
author_email: d.neustadt@shopware.com 
author_github: dneustadt
---
# Core
* Changed `Shopware\Core\Content\ProductStream\DataAbstractionLayer\ProductStreamIndexer::buildNested` to wrap filters for `product.id` in multi `OR` filter with an identical filter on `product.parentId`
___
# Administration
* Changed `sw-product-stream-modal-preview` to map filters for search with filters for `product.id` wrapped in multi `OR` filter with an identical filter on `product.parentId`
