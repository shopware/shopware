---
title: Do not include empty subcategory navigation lists
author: Max
author_email: max@swk-web.com
author_github: @aragon999
---
# Storefront
* Changed `@Storefront/storefront/layout/sidebar/category-navigation.html.twig` template to only include the subcategory navigation if there are any subcategories, avoiding empty lists displayed in the sidebar navigation
* Changed `@Storefront/storefront/layout/navbar/categories.html.twig` template to only include the subcategory navigation if there are any subcategories, avoiding empty `div`s displayed in the category navigation
