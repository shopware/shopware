---
title: Fix the custom field name on search module
issue: NEXT-14324
---
# Administration
* Changed block `sw_settings_search_smart_bar_actions_save` in `src/module/sw-settings-search/page/sw-settings-search/sw-settings-search.html.twig` to change button save from `sw-button` to `sw-button-process`.
* Changed `src/module/sw-settings-search/component/sw-settings-search-searchable-content-customfields/index.js` to filter added custom fields result by adding a criteria computed `customFieldFilteredCriteria`.
* Changed `src/module/sw-settings-search/component/sw-settings-search-searchable-content-customfields/index.js` to add method `showCustomFieldWithSet()` and `getMatchingCustomFields()`
to show the custom field with custom field set name.
* Changed `src/module/sw-settings-search/component/sw-settings-search-searchable-content-customfields/sw-settings-search-searchable-content-customfields.html.twig` to make the 
`#selection-label-property` and `#result-label-property` has the same result.
* Removed a test case `@settings: Can not create a config field which is exists` in  `src/Administration/Resources/app/administration/test/e2e/cypress/integration/settings/sw-settings-search/sw-settings-search-searchable-content/crud-custom-fields.spec.js` because the filter had already prevented this duplication case.
