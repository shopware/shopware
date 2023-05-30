---
title: Remember filter setting
issue: NEXT-22635
---
# Administration
* Added `value` prop in `sw-settings-snippet-filter-switch` component
* Added `filterSettings` prop in `sw-settings-snippet-sidebar` component
* Added the following computed properties in `sw-settings-snippet-sidebar` component:
    * `activeFilterNumber`
    * `isExpandedAuthorFilters`
    * `isExpandedMoreFilters`
* Added `resetAll` method in `sw-settings-snippet-sidebar` component
* Changed `snippetSetCriteria` computed property in `sw-settings-snippet-list` component
* Added the following computed properties in `sw-settings-snippet-list` component:
    * `queryIds`
    * `hasActiveFilters`
    * `activeFilters`
* Changed the following methods in `sw-settings-snippet-list` component:
    * `createdComponent`
    * `getList`
    * `initializeSnippetSet`
    * `onChange`
* Added the following methods in `sw-settings-snippet-list` component:
    * `beforeDestroyComponent`
    * `addEventListeners`
    * `removeEventListeners`
    * `beforeUnloadListener`
    * `getFilterSettings`
    * `getUserConfig`
    * `saveUserConfig`
    * `createFilterSettings`
    * `onResetAll`
