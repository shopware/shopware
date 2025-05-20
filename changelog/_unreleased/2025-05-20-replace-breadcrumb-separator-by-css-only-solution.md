---
title: Replace breadcrumb separator by CSS only solution
author: Max
author_email: max@swk-web.com
author_github: @aragon999
---
# Storefront
* Deprecated twig block `layout_breadcrumb_placeholder` from the template `@Storefront/storefront/layout/breadcrumb.html.twig` and replaced breadcrumb separator by a CSS only solution
* Changed the breadcrumb separator `.breadcrumb-placeholder` from a `div` to a list item, such that the breadcrumb list only contains `li` elements
