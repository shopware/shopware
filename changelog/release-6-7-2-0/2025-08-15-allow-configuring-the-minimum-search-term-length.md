---
title: allow configuring the minimum search term length
issue: #11510
---
# Administration
* Added the following methods in `search-ranking.service.js` module:
    * `getMinSearchTermLength`
    * `saveMinSearchTermLength`
* Added the following methods and computed properties in `sw-profile-index` component:
    * `minSearchTermLength`
    * `saveMinSearchTermLength`
* Added the following methods and computed properties in `sw-profile-index-search-preferences` component:
    * `minSearchTermLength`
    * `getMinSearchTermLength`
* Added `minSearchTermLength` state in `sw-profile.store.ts` module
* Added `sw_profile_index_search_preferences_search_behavior` block in `sw-profile-index-search-preferences` component template to allow users configure the minimum search term length
