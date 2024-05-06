---
title: Allow toggling the smart bar from an app module
issue: NEXT-34803
---
# Administration
* Changed `initMenuItems` method in `menu-item.init.ts` to add `displaySmartBar` to menu item config
* Changed `addModule` method in `extension-sdk-module.store.ts` to add `displaySmartBar` as a static element
* Added `showSmartBar` computed property in `sw-extension-sdk-module` to toggle the smart bar as needed
