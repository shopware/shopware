---
title: Display Cross Selling Element on Storefront
issue: NEXT-12063
---
# Core
* Added `Shopware\Core\Content\Product\Cms\CrossSellingCmsElementResolver` to resolve `cross-selling` cms element.
* Added `Shopware\Core\Content\Cms\SalesChannel\Struct\CrossSellingStruct.php` to handle data for `cross-selling` cms element.
___
# Storefront
* Added `src/Storefront/Resources/views/storefront/element/cms-element-cross-selling.html.twig` to display `cross-selling` cms element.
* Changed `src/Storefront/Resources/views/storefront/element/cms-element-product-slider.html.twig` to display products with `cross-selling` element.
