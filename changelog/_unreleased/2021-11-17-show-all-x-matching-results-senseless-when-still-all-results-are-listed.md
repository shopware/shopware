---
title: Show all x matching results senseless when still all results listed
issue: NEXT-18798
flag: FEATURE_NEXT_6040
---
# Administration
* Changed block `sw_search_bar_results_list_bar_item` in `src/app/component/structure/sw-search-bar/sw-search-bar.html.twig` to check if the `sw-search-more-results` is shown
* Changed method `buildGlobalSearchQueries` in `src/Administration/Resources/app/administration/src/app/service/search-ranking.service.js` to set limit for search criteria
* Changed `criteriaCollection` computed property in `src/app/component/structure/sw-search-bar/index.js` to set limit for search criteria
