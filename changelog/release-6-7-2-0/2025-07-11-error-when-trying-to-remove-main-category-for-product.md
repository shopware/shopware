---
title: Error when trying to remove "Main category" for product
issue: 8584
author: Le Nguyen
author_email: nguyenquocdaile@gmail.com
author_github: @Le Nguyen
---
# Administration
* Added `onRemoveMainCategory` method to remove main category for product in `sw-product-detail-seo` component.
* Changed `onMainCategorySelected` method to emit `main-category-remove` event when main category is removed in `sw-seo-main-category` component.
