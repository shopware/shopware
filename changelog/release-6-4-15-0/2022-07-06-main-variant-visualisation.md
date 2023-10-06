---
title: Main variant visualisation
issue: NEXT-17544
author: Simon Vorgers & Ramona Schwering
---
# Core
* Added class `Shopware\Core\Framework\DataAbstractionLayer\VariantListingConfig`
* Added class `Shopware\Core\Framework\DataAbstractionLayer\VariantListingConfigField`
* Added class `Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\VariantListingConfigFieldSerializer`
* Added `displayParent` to `Shopware\Core\Content\Product\ProductEntity`
* Added `variantListingConfig` to `Shopware\Core\Content\Product\ProductEntity`
* Added `display_parent` to `Shopware\Core\Content\Product\ProductDefinition`
* Added `variant_listing_config` to `Shopware\Core\Content\Product\ProductDefinition`
* Changed `Shopware\Core\Content\Product\SalesChannel\Detail\ProductDetailRoute`
* Changed `Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingLoader`
___
# Administration
* Changed `src/Administration/Resources/app/administration/src/module/sw-product/component/sw-product-variants/sw-product-variants-delivery/sw-product-variants-delivery-listing/sw-product-variants-delivery-listing.html.twig`, including its `index.js` and `scss` files to adapt to the new main variant handling
* Changed `src/administration/src/module/sw-product/component/sw-product-variants/sw-product-variants-delivery/sw-product-variants-delivery-listing/index.js`
* Changed `src/Administration/Resources/app/administration/src/app/component/form/select/entity/sw-entity-multi-select/sw-entity-multi-select.html.twig`
* Changed `src/Administration/Resources/app/administration/src/module/sw-product/helper/sw-products-variants-generator.js` to enable default values on variant generation
* Changed `src/Administration/Resources/app/administration/src/module/sw-product/helper/sw-products-variants-generator.js` to enable default values on variant generation
* Changed `src/Administration/Resources/app/administration/src/module/sw-product/snippet/en-GB.json` to add new snippet `listingExplanationModeMainProduct` and adjust `listingExplanationModeSingle`
* Changed `src/Administration/Resources/app/administration/src/module/sw-product/snippet/de-DE.json` to add new snippet `listingExplanationModeMainProduct` and adjust `listingExplanationModeSingle`
___
# Storefront
* Changed `component/product/card/box-standard.html.twig` to display the variant's properties depending on presentation selection
* Changed `component/product/card/price-unit.html.twig` to display the correct variant's unit and cheapest price depending on presentation selection
