---
title: Fix the breadcrumb for products assigned to multiple categories
issue: NEXT-11152
author_github: @Dominik28111
---
# Core
* Added parameter `EntityRepositoryInterface` to `Core\Content\Category\Service\CategoryBreadcrumbBuilder` constructor. This parameter will be required with 6.4.0.
* Added new method `getProductSeoCategory()` to `Core\Content\Category\Service\CategoryBreadcrumbBuilder` to get the category for the current sales channel of a product.
* Added new runtime field `seoCategory` to `Core\Content\Product\SalesChannel\SalesChannelProductEntity` which stores the category for the breadcrumb.
* Added parameter `CategoryBreadcrumbBuilder` to `Core\Content\Product\SalesChannel\Detail\ProductDetailRoute` constructor.
* Changed method `load()` in `Core\Content\Product\SalesChannel\Detail\ProductDetailRoute` it sets the seoCategory for the product now.
___
# Storefront
* Added parameter `CategoryBreadcrumbBuilder` to `Storefront\Page\Product\ProductPageLoader` constructor.
* Deprecated parameter `navigationTree` in `Storefront/Resources/views/storefront/page/content/index.html.twig`. This parameter will be removed in v6.4.0.
* Deprecated parameter `navigationTree`, `categoryTree` and `product` in `Storefront/Resources/views/storefront/page/product-detail/index.html.twig`. These parameters will be removed in v6.4.0.
