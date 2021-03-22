---
title: Implement searchable content with api integration
issue: NEXT-13010
---
# Administration
* Added some new props, data, computed, methods in `module/sw-settings-search/component/sw-settings-search-searchable-content-customfields/index.js`
    * Props: `columns`, `repository`, `searchConfigs`, `isLoading`.
    * Data: `customFields`.
    * Computed: `customFieldRepository`.
    * Methods: `getMatchingCustomFields`, `createdComponent`, `onInlineEditSave`, `onInlineEditCancel`, `onResetRanking`, `onRemove`.
* Changed `onAddField` method in `module/sw-settings-search/component/sw-settings-search-searchable-content-customfields/index.js`.
* Changed `sw-entity-listing` component in `module/sw-settings-search/component/sw-settings-search-searchable-content-customfields/sw-settings-search-searchable-content-customfields.html.twig`.
* Added some new props, data, computed, methods in `module/sw-settings-search/component/sw-settings-search-searchable-content-general/index.js`
    * Props: `columns`, `repository`, `searchConfigs`, `isLoading`.
    * Data: `customFields`.
    * Computed: `customFieldRepository`.
    * Methods: `getMatchingCustomFields`, `createdComponent`, `onInlineEditSave`, `onInlineEditCancel`, `onResetRanking`, `onRemove`.
* Changed `onAddField` method in `module/sw-settings-search/component/sw-settings-search-searchable-content-general/index.js`.
* Changed `sw-entity-listing` component in `module/sw-settings-search/component/sw-settings-search-searchable-content-general/sw-settings-search-searchable-content-general.html.twig`.
* Added some new props, data, computed, methods, watch in `module/sw-settings-search/component/sw-settings-search-searchable-content/index.js`
    * Props: `searchConfigId`.
    * Data: `isLoading`, `isEnabledReset`, `searchConfigFields`, `fieldConfigs`.
    * Computed: `productSearchFieldRepository`, `productSearchFieldCriteria`, `isListEmpty`.
    * Watch: `searchConfigId`.
    * Methods: `createNewConfigItem`, `getConfigFieldDefault`, `getProductSearchFieldColumns`, `loadData`, `saveConfig`, `deleteConfig`.
* Changed `onAddNewConfig`, `onChangeTab`, `onResetToDefault` method in `module/sw-settings-search/component/sw-settings-search-searchable-content/index.js`.
* Changed `sw-settings-search-searchable-content-general` component in `module/sw-settings-search/component/sw-settings-search-searchable-content/sw-settings-search-searchable-content.html.twig`.
* Changed `sw-settings-search-searchable-content-customfields` component in `module/sw-settings-search/component/sw-settings-search-searchable-content/sw-settings-search-searchable-content.html.twig`.
* Added some new data, watch in `module/sw-settings-search/view/sw-settings-search-view-general/index.js`
    * Data: `searchConfigId`.
    * Watch: `productSearchConfigs`.
* Add new `searchConfigId` prop into `sw-settings-search-searchable-content` component in `module/sw-settings-search/view/sw-settings-search-view-general/sw-settings-search-view-general.html.twig`.
