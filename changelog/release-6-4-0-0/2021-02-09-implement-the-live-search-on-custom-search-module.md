---
title: Implement the live search on custom search module
issue: NEXT-11967
---
# Administration
* Added new `sw-settings-search-live-search` component in `sw-settings-search` module for display live search.
* Added `sw-settings-search-live-search/sw-settings-search-live-search.html.twig` with these new blocks
    * `sw_settings_search_view_live_search_card`
    * `sw_settings_search_view_live_search_rebuild_index_row`
    * `sw_settings_search_view_live_search_description`
    * `sw_settings_search_view_live_search_sales_channel`
    * `sw_settings_search_view_live_search_input`
    * `sw_settings_search_view_live_search_results`
    * `sw_search_bar_results_empty_state`
    * `sw_settings_search_view_live_search_results_no_result`
    * `sw_settings_search_view_live_search_results_search_grid`
    * `sw_settings_search_view_live_search_results_search_grid_columns`
    * `sw_settings_search_view_live_search_results_search_grid_name`
    * `sw_settings_search_view_live_search_results_search_grid_score`
* Added `sw-settings-search-live-search/index.js` with these new data `liveSearchTerm`, `salesChannels`, `salesChannelId`, `liveSearchResults`, `searchInProgress`.
* Added `sw-settings-search-live-search/index.js` with these new props `currentSalesChannelId`, `searchTerms`, `searchResults` to keep the current data on back to live search tab.
* Added `sw-settings-search-live-search/index.js` with these new computed `salesChannelRepository`, `isSearchEnable`, `searchColumns`, `products`.
* Added `sw-settings-search-live-search/index.js` with these new methods `createdComponent()`, `searchOnStorefront()`, `fetchSalesChannels()`, `changeSalesChannel()`.
* Added new `sw-settings-search-live-search-keyword` component in `sw-settings-search` module for multiple keywords highlight.
    * Added block `sw_settings_search_view_live_search_keyword` and block `sw_settings_search_view_live_search_keyword_highlight` in `sw-settings-search-live-search-keyword.html.twig`
    * Added `sw-settings-search-live-search-keyword/index.js`
        * Added props `text`, `searchTerms`, `highlightClass`
        * Added computed `parsedSearch`, `parsedMsg`
        * Added method `getClass()`
* Added `init/services.init.js` in `sw-settings-search` module.
* Added `/src/module/sw-settings-search/service/livesearch.api.service.js` for proxy search on storefront.  
* Changed `/src/module/sw-settings-search/index.js` to import `sw-settings-search-live-search`, `sw-settings-search-live-search-keyword` and `services.init.js`.
* Changed `sw-settings-search/page/sw-settings-search/index.js` to add the data and methods
    * currentSalesChannelId
    * searchTerms 
    * searchResults
    * fetchSalesChannels()
    * onSalesChannelChanged()
    * onLiveSearchResultsChanged()
* Changed block `sw_setting_search_tabs_live_search` in `src/module/sw-settings-search/page/sw-settings-search/index.js` to bind all the props.
* Changed block `sw_settings_search_tabs_content` in `src/module/sw-settings-search/page/sw-settings-search/index.js` to bind these props and event binding.
    * productSeachConfigs
    * currentSalesChannelId
    * searchTerms
    * searchResults
    * @sales-channel-changed
    * @live-search-results-changed
* Changed `src/module/sw-settings-search/view/sw-settings-search-view-live-search/index.js` to add props
    * currentSalesChannelId
    * searchTerms
    * searchResults
* Added block `sw_settings_search_view_live_search_content` in  `/src/module/sw-settings-search/view/sw-settings-search-view-live-search/sw-settings-search-view-live-search.html.twig`
