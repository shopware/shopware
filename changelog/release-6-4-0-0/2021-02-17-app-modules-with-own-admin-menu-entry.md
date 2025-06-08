---
title: Own admin menu entries for app modules
issue: NEXT-13797
---
# Administration
* Added optional parameters `sorting` and `defaultPosition` to FlatTree constructor.
* Added `getNavigationFromAdminModules` to menuService.
* Added `getNavigationFromApps` to menuService.
* Added state `adminModuleNavigation` to `adminMenu` store.
* Added mutation `setAdminModuleNavigation` to `adminMenu` store.
* Added getter `appModuleNavigation` to `adminMenu` store.
* Added getter 'mainMenuEntries' to `adminMenu` store.
* Deprecated setters and getters for `defaultPosition` in FlatTree. Both will be removed.
* Deprecated `getRegisteredNodes` in FlatTree. It will be removed in future versions.
* Deprecated `getMainMenu` in menuService.
* Deprecated `addItem` and `removeItem` in menuService.
* Deprecated getter `navigation` in store `shopwareApps`. Use `adminMenu/appModuleNavigation` instead
* Deprecated computed `appEntries` in `sw-admin-menu`. Use `appModuleNavigation` instead
