---
title: Fix CMS Product Listing Config unable to deactivate previously enabled filters
issue: 4814
---
# Core
* Changed `Shopware\Core\Content\Product\Cms\ProductListingCmsElementResolver::restrictFilters` to use `getTranslation('config')` instead of `get('config')` so that category-level filter overrides are correctly applied, allowing previously enabled filters to be deactivated.
