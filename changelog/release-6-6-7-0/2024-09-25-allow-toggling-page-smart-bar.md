---
title: Allow toggling page smart bar
issue: NEXT-37661
---
# Administration
* Changed `@shopware-ag/meteor-admin-sdk` package to use the version `5.5.0`
* Changed `initMainModules` function in `main-module.init.ts` module to add the `smartBarHide` handler
* Added `hiddenSmartBars` state and `addHiddenSmartBar` mutation in `extension-sdk-module.store.ts` module
* Changed `sw-extension-app-module-page` component template to allow toggling page smart bar
