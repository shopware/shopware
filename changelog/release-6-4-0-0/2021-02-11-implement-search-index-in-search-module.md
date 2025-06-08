---
title: Implement search index in search module
issue: NEXT-11994
---
# Administration
* Added new `sw-settings-search-search-index` component in `sw-settings-search` module for display the rebuild search index section.
* Added `sw-settings-search-live-search/sw-settings-search-search-index.html.twig` with these new blocks
    * `sw_settings_search_search_index`
    * `sw_settings_search_search_index_description`
    * `sw_settings_search_search_index_warning_top`
    * `sw_settings_search_search_index_warning_bottom`
    * `sw_settings_search_search_index_rebuild_button`
    * `sw_settings_search_search_index_lastest_build`
    * `sw_settings_search_search_index_rebuild_progress`
    * `sw_settings_search_search_index_rebuild_progress_text`
    * `sw_settings_search_search_index_rebuild_progress_bar`
* Added `sw-settings-search-search-index/index.js` with these new data `isLoading`, `isRebuildSuccess`, `rebuildInProgress`, `progressBarValue`, `offset`, `syncPolling`, `totalProduct`, `latestProductIndexed`.
* Added `sw-settings-search-search-index/index.js` with these new computed `productRepository`, `productSearchKeywordRepository`, `productCriteria`, `productSearchKeywordsCriteria`, `latestBuild`.
* Added `sw-settings-search-search-index/index.js` with these new methods `createdComponent()`, `beforeDestroyComponent()`, `getTotalProduct()`, `updateProgress()`, `pollData()`, `clearPolling()`, `rebuildSearchIndex()`, `buildFinish()`.
* Added `/src/module/sw-settings-search/service/productIndex.api.service.js` for building products index.
* Changed `init/services.init.js` in `sw-settings-search` module to add the registration for `productIndexServive`
* Changed block `sw_setting_search_tabs_general` and `sw_setting_search_tabs_live_search` in `sw-settings-search/page/sw-settings-search/sw-settings-search.html.twig` to bind the `$props`.
* Changed the block `sw_settings_search_search_index_card` in `sw-settings-search/view/sw-settings-search-view-general/sw-settings-search-view-general.html.twig` to render the `sw-settings-search-search-index` component.
