---
title: Add support for third level menu entries in expanded menu
issue: NEXT-28681
author: Cedric Engler
author_email: cedric.engler@pickware.de
author_github: @Ceddy610
---
# Administration
* Added `currentExpandedMenuEntries` computed variable in `sw-admin-menu-item` component
* Changed `onMenuItemClick` method in `sw-admin-menu` component to handle the `navigation-list-item__level-2` style property
* Added the class property in `sw-admin-menu-item` template to handle the expanded sub menu items
