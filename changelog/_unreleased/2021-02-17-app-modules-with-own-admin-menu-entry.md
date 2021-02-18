---
title: Own admin menu entries for app modules
issue: NEXT-13797
---
# Administration
* Added optional parameters `sorting` and `defaultPosition` to FlatTree constructor.
* Added `getNavigationFromModules` to menuService.
* Deprecated setters and getters for `defaultPosition` in FlatTree. Both will be removed.
* Deprecated `getRegisteredNodes` in FlatTree. It will be removed in future versions.
* Deprecated `getMainMenu` in menuService.
* Deprecated `addItem` and `removeItem` in menuService.
