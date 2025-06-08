---
title: Display "Cross Selling" block on Storefront
issue: NEXT-12064
---
# Storefront
* Added `src/Storefront/Resources/views/storefront/block/cms-block-cross-selling.html.twig` to display `cross-selling` cms block.
* Changed method `collect()` in `Shopware\Core\Content\Product\Cms\CrossSellingCmsElementResolver` to handle get product data when used in PDP layout.
