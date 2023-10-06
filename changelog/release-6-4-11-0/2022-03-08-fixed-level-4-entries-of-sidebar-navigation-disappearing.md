---
title: Fixed level-4 entries of sidebar navigation disappearing
issue: NEXT-9461
author: Luka Brlek
author_email: l.brlek@shopware.com
---
# Core
* Added the `+ 1` for the depth calculcation of the navigation tree in `NavigationRoute` to fix the disapearing of the level-4 entries inside the navigation.
___
# Storefront
* Changed value of the `navigationMaxDepth` block in `category-navigation.html` file from fixed value to the value that can be changed via administration settings.