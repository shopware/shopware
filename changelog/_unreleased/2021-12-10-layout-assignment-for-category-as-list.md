---
title: Layout assignment for category as list
issue: NEXT-6758
author: Niklas Limberg
author_github: NiklasLimberg
author_email: n.limberg@shopware.com
---
# Administration
* Added the `sw-sorting-select` to unify the functionally of the sorting in the `sw-cms-layout-modal` and `sw-cms-list`
* Added the `onSort` method to `listing.mixin.js` to allow sorting changes when passing `{ sortBy: String, sortDirection: String }`
* Added `preSelection` capabilities to `sw-cms-layout-modal/index.js`
* Changed `sw-category-entry-point-modal.html.twig`, `sw-category-layout-card.html.twig` and `sw-product-detail-layout.html.twig` to pass `preSelection` to `sw-cms-layout-modal`
* Added `grid` view to `sw-cms-layout-modal`
* Changed `sw-cms-layout-modal.html.twig` and `sw-cms-layout-modal.scss` to implement the `grid` view