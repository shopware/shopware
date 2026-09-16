---
title: Apply hide closeout products when out of stock to stream sliders
issue: 15902
---
# Core
* Changed `ProductSliderCmsElementResolver::collectByProductStream()` to add a `ProductCloseoutFilter` to the product stream criteria before the slider limit is applied when `core.listing.hideCloseoutProductsWhenOutOfStock` is enabled for the sales channel. CMS product sliders sourced from a product stream now hide out-of-stock closeout products, like sliders with manually assigned products already did, and hidden products no longer consume slider slots.
* Changed `ProductSliderCmsElementResolver::enrich()` to also run the resolved stream products through the closeout filtering, because a stream hit can be remapped to its display parent or main variant, which may itself be a hidden closeout product.
* Changed `ProductSliderCmsElementResolver::filterOutOutOfStockHiddenCloseoutProducts()` to keep products that still have children, so a variant parent is not hidden because of its own stock.
* Added `Shopware\Core\Content\Product\SalesChannel\AbstractProductCloseoutFilterFactory` as a new constructor argument of the `@internal` `ProductSliderCmsElementResolver`.
