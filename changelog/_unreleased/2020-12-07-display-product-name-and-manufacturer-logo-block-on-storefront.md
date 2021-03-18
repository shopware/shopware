---
title: Display product name and manufacturer logo block on storefront
issue: NEXT-11744
---
# Core
* Added a new `Shopware\Core\Content\Product\Cms\ManufacturerLogoCmsElementResolver` to resolve `manufacturer-logo` cms element.
* Added a new `Shopware\Core\Content\Product\Cms\ProductNameCmsElementResolver` to resolve `product-name` cms element.
___
# Storefront
* Added new block `src/Storefront/Resources/views/storefront/block/cms-block-product-heading.html.twig` to render product detail's heading.
* Added new storefront's cms element `src/Storefront/Resources/views/storefront/element/cms-element-product-name.html.twig` to render cms element `product-name`. 
* Added new storefront's cms element `src/Storefront/Resources/views/storefront/element/cms-element-manufacturer-logo.html.twig` to render cms element `manufacturer-logo`. 
* Added new cms layout `src/Storefront/Resources/views/storefront/page/product-detail/cms/index.html.twig` to render product detail page layout.
* Changed `\Shopware\Storefront\Controller\ProductController::index` to render `src/Storefront/Resources/views/storefront/page/product-detail/cms/index.html.twig` when the product's cms page id is set.
* Changed `\Shopware\Storefront\Page\Product\ProductPageLoader::getCmsPage` to load the product's cms page id.
