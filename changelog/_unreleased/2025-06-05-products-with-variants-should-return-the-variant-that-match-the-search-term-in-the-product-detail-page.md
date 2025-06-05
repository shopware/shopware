---
title: Products with variants should return the variant that match the search term in the product detail page
issue: 10297
---
# Core
* Changed `Shopware\Core\Content\Product\SalesChannel\Detail\ProductDetailRoute` to return the variant that matches the search term in the product detail page.
___
# Storefront
* Changed `Storefront/Resources/views/storefront/component/product/card/box-standard.html.twig` to pass the search term to the product detail route.
* Changed `Storefront/Resources/views/storefront/layout/header/search-suggest.html.twig` to show the variant options in the search suggest results.
