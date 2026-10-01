---
title: Add active styling class to main navigation
issue: #9075
---
# Storefront
* Added new options `activeClass` and `pathIdList` to `NavbarPlugin`.
* Added new variable `navbarOptions` for JS-plugin configuration to `Resources/views/storefront/layout/navbar/navbar.html.twig`. 
* Deprecated file `Resources/views/storefront/layout/navigation/active-styling.html.twig`. The active styling class `.active` is set by `navbar.plugin.js`.
    * Deprecated block `layout_navigation_active_styling` because `active-styling.html.twig` will be removed.
    * Deprecated variable `navigationId` because `active-styling.html.twig` will be removed.
    * Deprecated include of file `active-styling.html.twig` in `Resources/views/storefront/base.html.twig`.
    * Deprecated block `base_navigation_styling` in `Resources/views/storefront/base.html.twig`. File `active-styling.html.twig` will be removed.
