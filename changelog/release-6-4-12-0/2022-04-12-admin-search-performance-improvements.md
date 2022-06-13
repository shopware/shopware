---
title: Admin search performance improvements
issue: NEXT-20963
---
# Administration
* Changed `doListSearch`, `doListSearchWithContainer` and `doGlobalSearch` methods in `src/app/component/structure/sw-search-bar/index.js` to change debounce time
* Changed `buildGlobalSearchQueries` method in `src/app/service/search-ranking.service.js` to set `totalCountMode` for criteria
* Changed `loadTypeSearchResults` method in `src/app/component/structure/sw-search-bar/index.js` to reset the limit and the totalCountMode for criteria
* Changed `searchTypeRoute` and `searchContent` methods in `src/app/component/structure/sw-search-more-results/index.js` to not show the total number of search results
