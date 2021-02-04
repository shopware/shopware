---
title: Menu behavior change
issue: NEXT-8235
author: Stephan Pohl
author_email: s.pohl@shopware.com 
author_github: @klarstil
---
# Administration
* Added ability to add a third level to the menu
* Added error prone behavior to the menu item entries
* Changed menu colours from grey accents to blue-grey accents
* Changed method `registerModule()` in `core/factory/module.factory.js` to add a default position of menu entries to 1000
* Added `borderColor` property to `sw-admin-menu-item`
* Added `getElementClasses` method to `sw-admin-menu-item`
* Added `mouseLocationTracked` & `subMenuDelay` properties to `sw-admin-menu`
* Added `subMenuTimer`, `mouseLocations`, `lastDelayLocation` & `activeEntry` data properties to `sw-admin-menu`
* Added `currentExpandedMenuEntries` computed property to `sw-admin-menu`
* Removed `openSubMenu`, `changeActiveItem`, `openFlyout`, `closeFlyout` & `getMenuItemClass` methods from `sw-admin-menu`
* Added `onMouseMoveDocument`, `onMenuItemClick`, `onMenuLeave`, `onMenuItemEnter`, `onSubMenuItemEnter`, `isPositionInPolygon`, `possiblyActivate`, `activateMenuItem`, `deactivatePreviousMenuItem`, `getPolygonFromMenuItem`, `getActivationDelay`, `onFlyoutEnter`, `onFlyoutLeave` & `removeClassesFromElements` methods to `sw-admin-menu`  
