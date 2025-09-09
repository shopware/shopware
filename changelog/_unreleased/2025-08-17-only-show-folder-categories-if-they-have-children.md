---
title: Only show folder categories if they have children
author: Max
author_email: max@swk-web.com
author_github: @aragon999
---
# Storefront
* Changed the templates `@Storefront/storefront/layout/navigation/offcanvas/categories.html.twig` and `@Storefront/storefront/layout/navbar/navbar.html.twig` to only show folder categories if they have children

___

# Upgrade Information

## Deprecation of `hasChildren` variable

Make sure to set the `hasChildren` variable in the `@Storefront/storefront/layout/navigation/offcanvas/categories.html.twig` template in the for loop where the category links are displayed and only display the links if the category is not of type `folder` or if it has children.

___

# Next Major Version Changes

## Removal of `hasChildren` variable 

The variable `hasChildren` is not set inside the `@Storefront/storefront/layout/navigation/offcanvas/item-link.html.twig` template anymore, as it should be set in the templates which include these templates. In the default templates this is done in the `@Storefront/storefront/layout/navigation/offcanvas/categories.html.twig` template.
