---
title: Fix product category selection unchecked
issue: NEXT-00000
author: Elias Lackner
author_email: lackner.elias@gmail.com
author_github: @lacknere
---
# Administration
* Changed `sw-category-tree-field` component to only make use of `selectedCategories` and `selectedCategoriesTotal` if `pageId` is set. Otherwise, use `categoriesCollection` directly.
