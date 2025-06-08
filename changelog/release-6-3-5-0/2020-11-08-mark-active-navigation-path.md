---
title: Mark active navigation path
issue: NEXT-11964
author: Sebastian König
author_email: s.koenig@tinect.de
author_github: @tinect
---
# Storefront
* Added style to mark active navigation `.navigation-flyout-link.active` in `skin/shopware/layout/_navigation-flyout.scss`
* Added variable `activePath` in `layout/navigation/categories.html.twig` in block `layout_navigation_categories`
* Added variable `activePath` in `layout/navigation/navigation.html.twig` in block `layout_main_navigation_menu_items`
* Added check for activePath for every `navigation-flyout-link` in block `layout_navigation_categories_item_link` in `layout/navigation/categories.html.twig` to mark complete active path
* Added check for activePath for every `main-navigation-link` in block `layout_main_navigation_menu_item` in `layout/navigation/navigation.html.twig` to mark complete active path
