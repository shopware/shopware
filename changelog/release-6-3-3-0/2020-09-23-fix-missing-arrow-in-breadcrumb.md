---
title: Fix missing arrow in breadcrumb
issue: NEXT-10960
author_github: @Dominik28111
---
# Storefront
*  Changed breadcrumb match for arrow placeholder from value to key in `component/listing/breadcrumb.html.twig` and `component/product/breadcrumb.html.twig` to avoid a missing arrow when the last element has the same name as another.
* Added new variable `breadcrumbKeys` in `component/listing/breadcrumb.html.twig`
* Added new variable `breadcrumbKeys` in `component/product/breadcrumb.html.twig`
