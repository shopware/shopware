---
title: Variant indicator missing in product listing
issue: 7447
---
# Core
* Changed `update` method in `Shopware\Core\Content\Product\DataAbstractionLayer\ProductIndexer` to skip update childCount for cloned products.
* Changed `Shopware\Core\Framework\DataAbstractionLayer\Field\ChildCountField` to allow system_scope context to write childCount.
___
# Administration
* Changed `sw-product-clone-modal` component to insert childCount for parent product when cloning a product.
