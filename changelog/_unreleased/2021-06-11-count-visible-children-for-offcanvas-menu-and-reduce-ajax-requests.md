---
title: Count visible children for offcanvas menu and reduce ajax requests
issue: NEXT-14732
author: d.neustadt
author_email: d.neustadt@shopware.com 
author_github: dneustadt
---
# Core
* Added `visibleChildrenCount` property to `CategoryEntity`
* Added aggregation of visible children count for last leafs of category tree in `NavigationLoader`
___
# Storefront
* Changed `item-link.html.twig` to use `visibleChildCount` to determine if menu item has children
* Changed `.js-navigation-offcanvas-initial-content` to have class to determine if is initial content of root
* Changed `offcanvas-menu.plugin.js` to not fetch offcanvas menu for root if initial content is of root already, reducing ajax requests
* Changed `back-link.html.twig` to use URL without `navigationId` if target is root as content of root is already cached without `navigationId`, reducing ajax requests
