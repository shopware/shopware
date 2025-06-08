---
title: Fix url of cms assets in administration
issue: NEXT-12471
---
# Administration
* Changed the cms admin components to use the asset filter instead of string concatenation to create asset urls, thus preventing duplicated slashes in the urls.
* Deprecated computed prop `contextAssetPath` of `sw-cms-el-image-slider` component.
