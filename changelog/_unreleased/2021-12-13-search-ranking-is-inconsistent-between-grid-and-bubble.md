---
title: Search ranking is inconsistent between product grid and bubble
issue: NEXT-19080
---
# Administration
* Added a new data property `freshSearchTerm` to mark if the grid is using a new term
* Added watchers for `term`, `sortBy`, `sortDirection` to update `freshSearchTerm` in `listing.mixin.js`
* Added a new computed property `currentSortBy` to check if the listing should use a sorting or not
