---
title: Mark active navigation path
issue: NEXT-11958
author: Sebastian König
author_email: s.koenig@tinect.de
author_github: @tinect
---
# Storefront
*  Add style to mark active navigation `.navigation-flyout-link.active` in `skin/shopware/layout/_navigation-flyout.scss`
*  Add variable `activePath` in `layout/navigation/categories.html.twig` in block `layout_navigation_categories`
*  Add variable `activePath` in `layout/navigation/navigation.html.twig` in block `layout_main_navigation_menu_items`
*  Add check for activePath for every `navigation-flyout-link` in block `layout_navigation_categories_item_link` in `layout/navigation/categories.html.twig` to mark complete active path
*  Add check for activePath for every `main-navigation-link` in block `layout_main_navigation_menu_item` in `layout/navigation/navigation.html.twig` to mark complete active path
