---
title: Adding variables and preview template format
issue: NEXT-21002
---
# Administration
* Changed method `load` in `src/app/component/entity/sw-one-to-many-grid/index.js` to add event `load-finish`.
* Added component `sw-settings-country-sidebar`, `sw-settings-country-preview-template-modal` in `src/module/sw-settings-country/component`.
* Added in `src/module/sw-settings-country/page/sw-settings-country-detail/sw-settings-country-detail.html.twig`
  * Added block `sw_settings_country_detail_sidebar` to show the right sidebar.
  * Added block `sw_settings_country_detail_preview_template_modal` to show preview template modal.
* Added in `src/module/sw-settings-country/page/sw-settings-country-detail/index.js`
  * Added method `openPreviewModal`, `showPreviewModal`, data variable `showPreviewModal` to handle showing preview tempalte modal.
  * Added computed property `showSidebar` to show right sidebar when navigate to Address handling tab.
  * Changed method `loadEntityData` to prevent infinity loop.
  * Added method `openPreviewModal` to open preview address modal.
  * Added method `closePreviewModal` to close preview address modal.
* Added in `src/module/sw-settings-country/page/sw-settings-country-detail/sw-settings-country-detail.html.twig`
  * Added block `sw_setting_country_tabs_address_handling` to show address-handling tab.
  * Added block `sw_setting_country_tabs_extension`
  * Added block `sw_settings_country_detail_preview_template_modal` to show preview address template modal.
  * Added block `sw_settings_country_detail_sidebar` to show sidebar.
* Changed in `src/module/sw-settings-country/component/sw-settings-country-state/index.js`
  * Added computed property `countryState`
  * Added watcher for `countryState`
  * Changed method `onSaveCountryState`
  * Changed method `onDeleteCountryStates` to reset selection of country state grid after deleting items.
  * Added method `getCountryStateName` to show country state name.
  * Added method `checkEmptyState` to check empty state of country state grid.
  * Added method `mountedComponent`
  * Changed method `getStateColumns` to show state name correctly.
* Changed in `src/module/sw-settings-country/index.js`
  * Added `address-handling` path as a child of country detail.
  * Added `address-handling` path as a child of country create.
