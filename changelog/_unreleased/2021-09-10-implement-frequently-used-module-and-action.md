---
title: Implement frequently used module & action
issue: NEXT-17082
---
# Administration
* Changed function `getModuleInfo` in `src/core/factory/router.factory.js` to fix load `$module` with `parentPath`.
* Added watcher `$route.name` in `src/app/component/structure/sw-desktop/index.js`.
* Added method `onUpdateSearchFrequently` in `src/app/component/structure/sw-desktop/index.js` to update user config with the current module.
* Added method `getModuleSearchFrequently` in `src/app/component/structure/sw-desktop/index.js` to get information current module.
* Added method `getModuleSearchFrequentlySpecialCases` in `src/app/component/structure/sw-desktop/index.js` to get current module information with special cases.
