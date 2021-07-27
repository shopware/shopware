---
title: Change the design of searching results
issue: NEXT-16308
flag: FEATURE_NEXT_6040
---
# Administration
* Changed method `loadTypeSearchResults` in `src/app/component/structure/sw-search-bar/index.js` to set limit criteria.
* Changed block `sw_search_bar_results_list` in `src/app/component/structure/sw-search-bar/sw-search-bar.html.twig`. 
* Added props `entityIconColor` and `entityIconName` in `src/app/component/structure/sw-search-bar-item/index.js`.
* Changed block `sw_search_bar_item` in `src/app/component/structure/sw-search-bar-item/sw-search-bar-item.html.twig` to update label.
* Added computed `searchContent` in `src/app/component/structure/sw-search-more-results/index.js` to show results content.
* Changed block `sw_search_more_results_content` in `src/app/component/structure/sw-search-more-results/sw-search-more-results.html.twig` to update slot content.
* Deprecated block `sw_search_bar_results_list_column_header_more_results` in `src/app/component/structure/sw-search-bar/sw-search-bar.html.twig` which can only be accessed using feature flag `FEATURE_NEXT_6040`. The deprecation will be removed in v6.5.0.0.
