---
title: Build product detail breadcrumb by referer
issue: NEXT-10187
author: Elias Lackner
author_email: lackner.elias@gmail.com
author_github: @lacknere
---
# Core
* Added `getCategoryByReferer` and `getCategoryByPath` methods to `CategoryBreadcrumbBuilder`.
* Added optional `$request` parameter to `getProductSeoCategory` method of `CategoryBreadcrumbBuilder`.
* Changed `getProductSeoCategory` method of `CategoryBreadcrumbBuilder` to consider last visited category by using `getCategoryByReferer` method.
* Added `$request` parameter to `getProductSeoCategory` method call in `load` method of `ProductDetailRoute`.
