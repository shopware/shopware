---
title: Fix CMS content language inheritance
issue: #16588
---
# Core
* Added `Shopware\Core\Content\Cms\Service\EntityCmsSlotConfigInheritanceBuilder` that walks the explicit language parent chain and merges `slot_config` field-by-field, so partial child-language overrides preserve parent-language fields they do not override.
* Changed `Shopware\Core\Content\Product\SalesChannel\Detail\ProductDetailRoute` to load product translations along the language inheritance chain (including the parent product) and merge their `slot_config` via the new builder before passing the result to the CMS page loader.
* Changed `Shopware\Core\Content\Category\SalesChannel\CategoryRoute` and `Shopware\Core\Content\LandingPage\SalesChannel\LandingPageRoute` to load the `translations` association and merge `slot_config` via the new builder.
