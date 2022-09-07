---
title: Uniform navigation item name
issue: NEXT-22676
author: Tommy Quissens
author_email: tommy.quissens@gmail.com
author_github: quisse
---
# Storefront
* Changed `item` to `treeItem` in navigation/offcanvas layouts so that `treeItem` is used everywhere. `treeItem` is also more clear what it's about.
* Deprecated variable `item` in the following twig templates. Use `treeItem` instead.
    * `Resources/views/storefront/layout/navigation/offcanvas/active-item-link.html.twig`.
    * `Resources/views/storefront/layout/navigation/offcanvas/back-link.html.twig`.
    * `Resources/views/storefront/layout/navigation/offcanvas/item-link.html.twig`.
    * `Resources/views/storefront/layout/navigation/offcanvas/show-active-link.html.twig`.
* Deprecated passing variable `item` to include templates inside `Resources/views/storefront/layout/navigation/offcanvas/categories.html.twig`. Variable `treeItem` will be passed instead.
