---
title: Add sales filter to product filtering
issue: NEXT-24029
author: Melvin Achterhuis
author_email: melvin.achterhuis@iodigital.com
author_github: MelvinAchterhuis
---

# Administration
* Changed computed `productFilters()` in `src/Administration/Resources/app/administration/src/app/component/form/select/entity/advanced-selection-entities/sw-advanced-selection-product/index.js`, add new filter
* Changed computed `listFilterOptions()` & `defaultFilters` in `src/Administration/Resources/app/administration/src/module/sw-product/page/sw-product-list/index.js`
* Changed computed `productColumns()` in `src/Administration/Resources/app/administration/src/module/sw-product/page/sw-product-list/index.js`, add new column
* Changed computed `variantColumns()` in `src/Administration/Resources/app/administration/src/module/sw-product/component/sw-product-variants/sw-product-variants-overview/index.js`, add new column
* Changed computed `gridColumns()` in `src/Administration/Resources/app/administration/src/module/sw-product/component/sw-product-variant-modal/index.js`, add new column
* Changed `src/Administration/Resources/app/administration/src/module/sw-product/page/sw-product-list/sw-product-list.html.twig`, add new block with logic for showing sales
* Added new snippets

