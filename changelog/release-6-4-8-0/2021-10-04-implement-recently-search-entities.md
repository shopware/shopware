---
title: Implement recently search entities
issue: NEXT-16574
---
# Administration
* Added a `RecentlySearchService` in `src/app/service/recently-search.service.js` to get and save clicked recently search entities into localStorage.
* Added a new method `onClickSearchResult` in `sw-search-bar-item` component to save clicked search result into localStorage stack.
* Added a new method `loadRecentlySearch` in `sw-search-bar` component to load recently search entities when do global search.
