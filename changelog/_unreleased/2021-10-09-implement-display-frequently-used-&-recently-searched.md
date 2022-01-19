---
title: Display frequently used and recently searched
issue: NEXT-17083
---
# Administration
* Added watcher `$route.name` in `src/app/component/structure/sw-search-bar/index.js` to listen route name.
* Changed method `onFocusInput` in `src/app/component/structure/sw-search-bar/index.js`.
* Changed `src/app/component/structure/sw-search-bar/index.js` to set `showResultsSearchTrends` in these methods:
    * `onSearchTermChange`
    * `showTypeContainer`
    * `setSearchType`
    * `doGlobalSearch`
* Changed `src/app/component/structure/sw-search-bar/index.js` to resolve response in these methods:
    * `loadSalesChannelType`
    * `loadSalesChannel`
* Changed method `getSalesChannelsBySearchTerm` in `src/app/component/structure/sw-search-bar/index.js`.
* Added method `loadSearchTrends` in `src/app/component/structure/sw-search-bar/index.js` to load all promise recently searched and frequently used.
* Added method `getFrequentlyUsedModules` in `src/app/component/structure/sw-search-bar/index.js` to get frequently used modules.
* Added method `getInfoModuleFrequentlyUsed` in `src/app/component/structure/sw-search-bar/index.js` to get module.
* Added block `sw_search_bar_trends_results` in `src/app/component/structure/sw-search-bar/sw-search-bar.html.twig` to display recently search and frequently used modules.
* Changed some computes in `src/app/component/structure/sw-search-bar-item/index.js` to get response with type `frequently_used`.
    * `moduleName`
    * `iconName`
    * `iconColor`
* Changed block `sw_search_bar_item_module` in `src/app/component/structure/sw-search-bar-item/sw-search-bar-item.html.twig` to update `v-if` includes `frequently_used` type.
* Added function `getIncrement` in `src/core/service/api/user-activity.service.js`.
* Added function `getModuleByName` in `src/core/factory/module.factory.js` to get module by `moduleName`.
