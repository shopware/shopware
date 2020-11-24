---
title: Add same canonical url for variants switch
issue: NEXT-12251
flag: FEATURE_NEXT_10820
---
# Core
* Added `canonical_product_id` to `Shopware\Core\Content\Product\ProductDefinition`
* Added `canonicalProduct` many to one association to `Shopware\Core\Content\Product\ProductDefinition`
* Added `$canonicalProductId` to `Shopware\Core\Content\Product\ProductEntity`
* Added `$canonicalProduct` to `Shopware\Core\Content\Product\ProductEntity`
* Added `getCanonicalProductId` getter to `Shopware\Core\Content\Product\ProductEntity`
* Added `setCanonicalProductId` setter `Shopware\Core\Content\Product\ProductEntity`
* Added `getCanonicalProduct` getter `Shopware\Core\Content\Product\ProductEntity`
* Added `setCanonicalProduct` setter `Shopware\Core\Content\Product\ProductEntity`
* Added `src/Core/Migration/Migration1606310257AddCanonicalUrlProp.php`
___
# Administration
* Added `sw_product_seo_form_canonical_url` block to `src/module/sw-product/component/sw-product-seo-form/sw-product-seo-form.html.twig`
* Added `sw_product_seo_form_canonical_url_switch` block to `src/module/sw-product/component/sw-product-seo-form/sw-product-seo-form.html.twig`
* Added `sw_product_seo_form_canonical_url_select` block to `src/module/sw-product/component/sw-product-seo-form/sw-product-seo-form.html.twig`
* Added `sw_product_seo_form_canonical_url_select_selection` block to `src/module/sw-product/component/sw-product-seo-form/sw-product-seo-form.html.twig`
* Added `sw_product_seo_form_canonical_url_select_result` block to `src/module/sw-product/component/sw-product-seo-form/sw-product-seo-form.html.twig`
___
# Storefront
* Deprecated link element in `layout_head_canonical` block in `app/administration/src/module/sw-product/component/sw-product-seo-form/sw-product-seo-form.html.twig`
* Added `layout_head_canonical_url` block in `app/administration/src/module/sw-product/component/sw-product-seo-form/sw-product-seo-form.html.twig`
